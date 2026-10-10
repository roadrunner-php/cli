<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Downloader;

use Spiral\RoadRunner\Console\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\DLoadGetSpy;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Output\BufferedOutput;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class DLoadDownloaderTest
{
    private string $dir;

    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
    }

    #[AfterTest]
    public function removeDirectory(): void
    {
        TempDirectory::remove($this->dir);
    }

    public function passesEnvironmentToDLoad(): void
    {
        $get = new DLoadGetSpy();

        $code = $this->download($get, constraint: '^2024.1', stability: 'beta', force: false);

        Assert::same($code, 0);
        Assert::count($get->calls, 1);
        $input = $get->calls[0];
        Assert::same($input->getArgument('software'), ['rr:^2024.1@beta']);
        Assert::same($input->getOption('path'), $this->dir);
        Assert::same($input->getOption('os'), 'linux');
        Assert::same($input->getOption('arch'), 'arm64');
        Assert::same($input->getOption('stability'), 'beta');
        Assert::false($input->getOption('force'));
        Assert::true($input->getOption('refresh'));
        Assert::false($input->isInteractive());
    }

    public function passesForceFlag(): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, force: true);

        Assert::true($get->calls[0]->getOption('force'));
    }

    /**
     * A project's `./dload.xml` must not take part in the download.
     */
    public function usesBundledEmptyConfig(): void
    {
        $get = new DLoadGetSpy();

        $this->download($get);

        $config = (string) $get->calls[0]->getOption('config');
        Assert::true(\is_file($config));
        Assert::same(\simplexml_load_file($config)?->getName(), 'dload');
        Assert::same(\simplexml_load_file($config)?->count(), 0);
    }

    #[DataSet(['https://ghe.example.com/api/v3', 'https://ghe.example.com'])]
    #[DataSet(['https://GHE.example.com/api/v3/', 'https://ghe.example.com'], 'trailing slash')]
    #[DataSet(['http://127.0.0.1:8080/api/v3', 'http://127.0.0.1:8080'], 'scheme and port are kept')]
    public function pointsRegistryAtEnterpriseServer(string $url, string $server): void
    {
        $get = new DLoadGetSpy();

        (new DLoadDownloader($get, $url))
            ->download('rr', '*', 'stable', 'linux', 'amd64', $this->dir, false, new BufferedOutput());

        $config = \simplexml_load_string($get->configs[0]);
        Assert::notSame($config, false);
        $servers = [];
        foreach ($config->registry->software as $software) {
            $servers[(string) $software['alias']] = (string) $software->repository['server'];
        }
        Assert::same($servers, ['rr' => $server, 'protoc-gen-php-grpc' => $server]);
        Assert::false(\is_file((string) $get->calls[0]->getOption('config')));
    }

    #[DataSet(['https://api.github.com'])]
    #[DataSet(['https://api.github.com/'], 'trailing slash')]
    #[DataSet([''], 'empty')]
    public function keepsBundledConfigForPublicGitHub(string $url): void
    {
        $get = new DLoadGetSpy();

        (new DLoadDownloader($get, $url))
            ->download('rr', '*', 'stable', 'linux', 'amd64', $this->dir, false, new BufferedOutput());

        Assert::same(\simplexml_load_string($get->configs[0])?->count(), 0);
    }

    #[DataSet(['http://'], 'no host')]
    #[DataSet(['ghe.example.com/api/v3'], 'no scheme')]
    #[DataSet(['ftp://ghe.example.com/api/v3'], 'unsupported scheme')]
    #[DataSet(['https://ghe.example.com'], 'no API path')]
    #[DataSet(['http://127.0.0.1:8080/github'], 'other API path')]
    #[DataSet(['https://api.github.com/api/v3'], 'public GitHub with enterprise path')]
    #[DataSet(['https://user:secret@ghe.example.com/api/v3'], 'credentials')]
    public function rejectsGitHubApiUrlDLoadCanNotUse(string $url): never
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('RR_GITHUB_API_URL');

        (new DLoadDownloader(new DLoadGetSpy(), $url))
            ->download('rr', '*', 'stable', 'linux', 'amd64', $this->dir, false, new BufferedOutput());
    }

    public function readsGitHubApiUrlFromEnvironment(): void
    {
        $get = new DLoadGetSpy();
        $_SERVER['RR_GITHUB_API_URL'] = 'http://127.0.0.1:8080/api/v3';

        try {
            $this->download($get);
        } finally {
            unset($_SERVER['RR_GITHUB_API_URL']);
        }

        Assert::string($get->configs[0])->contains('server="http://127.0.0.1:8080"');
    }

    #[DataSet(['3.*', 'stable', 'rr:3.*@stable'])]
    #[DataSet([' 2025.1.* ', 'rc', 'rr:2025.1.*@rc'], 'trimmed')]
    #[DataSet(['3.*@beta', 'stable', 'rr:3.*@beta'], 'explicit stability')]
    #[DataSet(['*', 'beta', 'rr'], 'any version')]
    #[DataSet(['', 'stable', 'rr'], 'empty constraint')]
    public function putsStabilityIntoVersionConstraint(string $constraint, string $stability, string $software): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, constraint: $constraint, stability: $stability);

        Assert::same($get->calls[0]->getArgument('software'), [$software]);
        Assert::same($get->calls[0]->getOption('stability'), $stability);
    }

    public function returnsDLoadExitCode(): void
    {
        Assert::same($this->download(new DLoadGetSpy(exitCode: 1)), 1);
    }

    public function forwardsDLoadOutput(): void
    {
        $output = new BufferedOutput();

        (new DLoadDownloader(new DLoadGetSpy()))
            ->download('protoc-gen-php-grpc', '2025.*', 'stable', 'linux', 'amd64', $this->dir, true, $output);

        Assert::same($output->fetch(), 'dload: protoc-gen-php-grpc:2025.*@stable' . \PHP_EOL);
    }

    /**
     * DLoad's own `get` command is used unless another one is given.
     */
    public function usesDLoadGetCommandByDefault(): void
    {
        // DLoad validates the stability before it resolves or downloads anything
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Unknown stability level: unknown');

        (new DLoadDownloader())->download(
            software: 'rr',
            constraint: '*',
            stability: 'unknown',
            os: 'linux',
            arch: 'amd64',
            location: $this->dir,
            force: false,
            output: new BufferedOutput(),
        );
    }

    private function download(
        DLoadGetSpy $get,
        string $constraint = '*',
        string $stability = 'stable',
        bool $force = false,
    ): int {
        return (new DLoadDownloader($get))->download(
            software: 'rr',
            constraint: $constraint,
            stability: $stability,
            os: 'linux',
            arch: 'arm64',
            location: $this->dir,
            force: $force,
            output: new BufferedOutput(),
        );
    }
}
