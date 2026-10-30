<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Downloader;

use Spiral\RoadRunner\Console\Internal\Downloader\DLoadDownloader;
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
        $repositories = [];
        foreach ($config->registry->software as $software) {
            $repositories[(string) $software['alias']] = [
                (string) $software->repository['uri'],
                isset($software->repository['server']),
            ];
        }
        Assert::same($repositories, [
            'rr' => [$server . '/roadrunner-server/roadrunner', false],
            'protoc-gen-php-grpc' => [$server . '/roadrunner-server/roadrunner', false],
            'protoc' => [$server . '/protocolbuffers/protobuf', false],
            'buf' => [$server . '/bufbuild/buf', false],
        ]);
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

        Assert::string($get->configs[0])->contains('uri="http://127.0.0.1:8080/roadrunner-server/roadrunner"');
    }

    #[DataSet(['3.*', 'stable', 'rr:3.*@stable'])]
    #[DataSet([' 2025.1.* ', 'rc', 'rr:2025.1.*@rc'], 'trimmed')]
    #[DataSet(['3.*@beta', 'stable', 'rr:3.*@beta'], 'explicit stability')]
    public function putsStabilityIntoVersionConstraint(string $constraint, string $stability, string $software): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, constraint: $constraint, stability: $stability);

        Assert::same($get->calls[0]->getArgument('software'), [$software]);
        Assert::same($get->calls[0]->getOption('stability'), $stability);
    }

    /**
     * Calendar releases (2025.x) rank above 3.x numerically, so "any version" must not reach DLoad unbounded.
     */
    #[DataSet(['*', 'stable', 'rr:>=3.0 <2000@stable'])]
    #[DataSet([' * ', 'beta', 'rr:>=3.0 <2000@beta'], 'trimmed')]
    #[DataSet(['', 'rc', 'rr:>=3.0 <2000@rc'], 'empty constraint')]
    #[DataSet(['*@beta', 'stable', 'rr:>=3.0 <2000@beta'], 'explicit stability')]
    #[DataSet(['@alpha', 'stable', 'rr:>=3.0 <2000@alpha'], 'stability only')]
    public function treatsWildcardAsAnySemverRelease(string $constraint, string $stability, string $software): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, constraint: $constraint, stability: $stability);

        Assert::same($get->calls[0]->getArgument('software'), [$software]);
    }

    #[DataSet(['>=3.0', 'rr:>=3.0 <2000@stable'], 'lower bound')]
    #[DataSet(['>= 2.12', 'rr:>= 2.12 <2000@stable'], 'spaced operator')]
    #[DataSet(['!=3.1.0', 'rr:!=3.1.0 <2000@stable'], 'exclusion')]
    #[DataSet(['<>3.1.0', 'rr:<>3.1.0 <2000@stable'], 'exclusion as <>')]
    #[DataSet(['>=3.0@beta', 'rr:>=3.0 <2000@beta'], 'explicit stability')]
    #[DataSet(['3.* || >=3.2', 'rr:3.* || >=3.2 <2000@stable'], 'each alternative')]
    #[DataSet(['>=3.0 | 2025.*', 'rr:>=3.0 <2000 || 2025.*@stable'], 'single pipe')]
    public function keepsUnboundedFilterBelowCalendarReleases(string $constraint, string $software): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, constraint: $constraint);

        Assert::same($get->calls[0]->getArgument('software'), [$software]);
    }

    #[DataSet(['3.4.0'], 'exact release')]
    #[DataSet(['3.5.0-beta.1'], 'exact pre-release')]
    #[DataSet(['^3.2'], 'caret')]
    #[DataSet(['~3.2.0'], 'tilde')]
    #[DataSet(['3.x'], 'wildcard')]
    #[DataSet(['>=3.0 <3.4'], 'upper bound')]
    #[DataSet(['3.0 - 3.4'], 'hyphen range')]
    #[DataSet(['3.* || 2025.*'], 'calendar alternative')]
    #[DataSet(['2025.*'], 'calendar wildcard')]
    #[DataSet(['2025.1.5'], 'calendar release')]
    #[DataSet(['>=2024'], 'calendar lower bound')]
    public function keepsBoundedOrCalendarFilter(string $constraint): void
    {
        $get = new DLoadGetSpy();

        $this->download($get, constraint: $constraint, stability: 'beta');

        Assert::same($get->calls[0]->getArgument('software'), ['rr:' . $constraint . '@beta']);
    }

    /**
     * Only the RoadRunner repository has calendar releases to keep out.
     */
    #[DataSet(['buf', '*', 'buf:*@stable'], 'wildcard')]
    #[DataSet(['protoc', '', 'protoc:*@stable'], 'empty constraint')]
    #[DataSet(['protoc', '*@rc', 'protoc:*@rc'], 'explicit stability')]
    #[DataSet(['protoc', '>=30.0', 'protoc:>=30.0@stable'], 'lower bound')]
    #[DataSet(['buf', '^1 || >=2.1', 'buf:^1 || >=2.1@stable'], 'alternatives')]
    #[DataSet(['protoc-gen-php-grpc', '>=3.0', 'protoc-gen-php-grpc:>=3.0 <2000@stable'], 'RoadRunner plugin')]
    public function boundsOnlyRoadRunnerSoftware(string $software, string $constraint, string $expected): void
    {
        $get = new DLoadGetSpy();

        (new DLoadDownloader($get))
            ->download($software, $constraint, 'stable', 'linux', 'amd64', $this->dir, false, new BufferedOutput());

        Assert::same($get->calls[0]->getArgument('software'), [$expected]);
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
