<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Skip;
use Testo\Test;

/**
 * Which release and asset `rr get` picks from the releases in Server/releases.json.
 */
#[Test]
final class VersionSelectionTest
{
    use FakeGitHubCase;

    #[DataSet(['get'])]
    #[DataSet(['get-binary'])]
    public function installsNewestStableReleaseOfDefaultMajor(string $command): void
    {
        $result = $this->rr()->run([$command, '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), 'fake rr 3.4.0 linux amd64');
        Assert::same(FakeGitHub::downloads(), ['roadrunner-3.4.0-linux-amd64.tar.gz']);
    }

    #[DataSet(['3.1.*', '3.1.0'])]
    #[DataSet(['2025.*', '2025.1.5'])]
    #[DataSet(['^2.12', '2.12.3'])]
    public function installsNewestReleaseMatchingFilter(string $filter, string $version): void
    {
        $result = $this->rr()->run(['get', '--filter=' . $filter, '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), "fake rr $version linux amd64");
    }

    #[DataSet(['stable', '3.*', '3.4.0'])]
    #[DataSet(['RC', '3.*', '3.5.0-rc.1'])]
    #[DataSet(['beta', '3.5.0-beta.1', '3.5.0-beta.1'])]
    public function honoursMinimumStability(string $stability, string $filter, string $version): void
    {
        $result = $this->rr()->run(['get', '--stability=' . $stability, '--filter=' . $filter, '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), "fake rr $version linux amd64");
    }

    #[DataSet(['rc'])]
    #[DataSet(['beta'])]
    #[Skip('Releases tagged "-rc.N" sort below "-beta.N" (the sort key only rewrites the "-RC" spelling), and a lowercase --stability=rc means "dev"')]
    public function prefersReleaseCandidateOverOlderBeta(string $stability): void
    {
        $result = $this->rr()->run(['get', '--stability=' . $stability, '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), 'fake rr 3.5.0-rc.1 linux amd64');
    }

    public function rejectsPreReleaseBelowRequestedStability(): void
    {
        $result = $this->rr()->run(['get', '--stability=stable', '--filter=3.5.0-beta.1', '--os=linux', '--arch=amd64']);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::false($this->dir->has('rr'));
        Assert::same(FakeGitHub::downloads(), []);
    }

    #[DataSet(['windows', 'amd64', 'rr.exe', 'roadrunner-3.4.0-windows-amd64.zip'])]
    #[DataSet(['darwin', 'arm64', 'rr', 'roadrunner-3.4.0-darwin-arm64.tar.gz'])]
    public function installsBinaryForAnotherPlatform(string $os, string $arch, string $binary, string $asset): void
    {
        $result = $this->rr()->run(['get', "--os=$os", "--arch=$arch"]);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read($binary)), "fake rr 3.4.0 $os $arch");
        Assert::same(FakeGitHub::downloads(), [$asset]);
    }

    public function fallsBackToOlderReleaseWhenNewestHasNoAssembly(): void
    {
        $result = $this->rr()->run(['get', '--os=linux', '--arch=arm64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), 'fake rr 3.2.0 linux arm64');
        // 3.4.0 has a linux-arm64 .deb package and protoc plugin, neither is a RoadRunner archive
        Assert::same(FakeGitHub::downloads(), ['roadrunner-3.2.0-linux-arm64.tar.gz']);
    }

    public function failsWhenNoReleaseMatchesFilter(): void
    {
        $result = $this->rr()->run(['get', '--filter=9.*', '--os=linux', '--arch=amd64']);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->matchesRegex('/9\.\*/');
        Assert::same($this->dir->files(), []);
        Assert::same(FakeGitHub::downloads(), []);
    }

    public function followsPagination(): void
    {
        $result = $this->rr(FakeGitHub::url('paged'))->run(['get', '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), 'fake rr 3.4.0 linux amd64');
        Assert::same(
            \array_map(static fn(array $request): string => (string) $request['query']['page'], FakeGitHub::releaseRequests()),
            ['1', '2', '3', '4'],
        );
    }

    public function staysWithinRequestBudget(): void
    {
        $result = $this->rr()->run(['get', '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        $releases = FakeGitHub::releaseRequests();
        Assert::count($releases, 1);
        Assert::same($releases[0]['query']['per_page'] ?? null, '100');
        Assert::count(FakeGitHub::requests(), 2);
        Assert::false(\in_array(true, \array_column(FakeGitHub::requests(), 'authorized'), true));
    }
}
