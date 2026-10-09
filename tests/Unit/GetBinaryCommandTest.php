<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\GetBinaryCommand;
use Spiral\RoadRunner\Console\Repository\ReleaseInterface;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\InMemoryRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class GetBinaryCommandTest
{
    private string $dir;
    private string $target;

    /** @var list<string> */
    private array $downloads = [];

    private string $cwd;

    /**
     * The command also looks for ".rr.yaml" in the working directory, so each test gets its own.
     */
    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
        $this->target = $this->dir . '/bin';
        \mkdir($this->target);
        $this->cwd = (string) \getcwd();
        \mkdir($this->dir . '/cwd');
        \chdir($this->dir . '/cwd');
    }

    #[AfterTest]
    public function removeDirectory(): void
    {
        \chdir($this->cwd);
        TempDirectory::remove($this->dir);
    }

    public function describesItself(): void
    {
        $command = new GetBinaryCommand();

        Assert::same($command->getName(), 'get-binary');
        Assert::same($command->getDescription(), 'Install or update RoadRunner binary');
    }

    public function installsBinaryOfNewestMatchingRelease(): void
    {
        $tester = $this->tester(
            $this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']),
            $this->release('v2024.2.0', [
                'roadrunner-2024.2.0-linux-amd64.deb',
                'roadrunner-2024.2.0-linux-amd64.zip',
                'roadrunner-2024.2.0-darwin-amd64.zip',
                'roadrunner-2024.2.0-linux-arm64.zip',
            ]),
            $this->release('v2024.3.0-beta.1', ['roadrunner-2024.3.0-beta.1-linux-amd64.zip']),
            $this->release('v2025.1.0', ['roadrunner-2025.1.0-linux-amd64.zip']),
        );

        $status = $tester->execute($this->input(['--no-config' => true]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::same($this->downloads, ['https://example.com/download/roadrunner-2024.2.0-linux-amd64.zip']);
        Assert::same(\file_get_contents($this->target . '/rr'), 'binary v2024.2.0');
        Assert::false(\is_file($this->target . '/.rr.yaml'));
        Assert::string($tester->getDisplay())
            ->contains('roadrunner-server/roadrunner (v2024.2.0): Downloading...')
            ->contains('RoadRunner (v2024.2.0) has been installed into');
    }

    public function skipsReleasesWithoutSuitableAssembly(): void
    {
        $tester = $this->tester(
            $this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']),
            $this->release('v2024.2.0', ['roadrunner-2024.2.0-darwin-amd64.zip']),
        );

        $status = $tester->execute($this->input(['--no-config' => true]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::same(\file_get_contents($this->target . '/rr'), 'binary v2024.1.0');
        Assert::string($tester->getDisplay())
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('roadrunner-server/roadrunner v2024.2.0 does not contain available assembly');
    }

    public function failsWhenNoReleaseHasSuitableAssembly(): never
    {
        $tester = $this->tester($this->release('v2024.1.0', ['roadrunner-2024.1.0-darwin-amd64.zip']));

        Expect::exception(\UnexpectedValueException::class)
            ->withMessageContaining('(--os=linux --arch=amd64 --stability=stable). Available: v2024.1.0');

        $tester->execute($this->input(['--no-config' => true]), ['interactive' => false]);
    }

    public function generatesConfigurationForPreset(): void
    {
        $tester = $this->tester($this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']));

        $status = $tester->execute($this->input(['--preset' => 'web']), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::array(\array_keys(Yaml::parseFile($this->target . '/.rr.yaml')))
            ->sameElementsAs(['version', 'rpc', 'http', 'jobs', 'server']);
    }

    public function keepsExistingConfiguration(): void
    {
        \file_put_contents($this->target . '/.rr.yaml', 'existing');
        $tester = $this->tester($this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']));

        $status = $tester->execute($this->input(['--plugin' => ['kv']]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::same(\file_get_contents($this->target . '/.rr.yaml'), 'existing');
    }

    public function keepsExistingBinaryUnlessConfirmed(): void
    {
        \file_put_contents($this->target . '/rr', 'old binary');
        $tester = $this->tester($this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']));

        $tester->execute($this->input(['--no-config' => true]), ['interactive' => false]);

        Assert::same(\file_get_contents($this->target . '/rr'), 'old binary');
        Assert::string($tester->getDisplay())
            ->contains('RoadRunner binary file already exists!')
            ->contains('Skipping RoadRunner installation...');
    }

    public function overwritesExistingBinaryWhenConfirmed(): void
    {
        \file_put_contents($this->target . '/rr', 'old binary');
        $tester = $this->tester($this->release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.zip']));
        $tester->setInputs(['yes']);

        $tester->execute($this->input(['--no-config' => true]));

        Assert::same(\file_get_contents($this->target . '/rr'), 'binary v2024.1.0');
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function input(array $input): array
    {
        return [
            '--location' => $this->target,
            '--os' => 'linux',
            '--arch' => 'amd64',
            '--filter' => '^2024.1',
        ] + $input;
    }

    /**
     * Creates a release whose assets are zip archives with an "rr" file named after the release.
     *
     * @param list<string> $assets
     */
    private function release(string $tag, array $assets): ReleaseInterface
    {
        $archive = TempDirectory::archive($this->dir, \bin2hex(\random_bytes(4)) . '.zip', [
            'roadrunner/rr' => 'binary ' . $tag,
        ]);
        $content = (string) \file_get_contents($archive);
        $client = new MockHttpClient(function (string $method, string $url) use ($content): MockResponse {
            $this->downloads[] = $url;

            return new MockResponse($content);
        });

        return Releases::release($tag, $assets, $client);
    }

    private function tester(ReleaseInterface ...$releases): CommandTester
    {
        $command = new class(new InMemoryRepository(...$releases)) extends GetBinaryCommand {
            public function __construct(
                private readonly RepositoryInterface $repository,
            ) {
                parent::__construct();
            }

            protected function getRepository(): RepositoryInterface
            {
                return $this->repository;
            }
        };

        return new CommandTester($command);
    }
}
