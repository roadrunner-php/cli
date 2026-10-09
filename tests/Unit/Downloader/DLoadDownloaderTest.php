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
