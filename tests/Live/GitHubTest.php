<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Live;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\Rr;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\Workdir;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * `rr` against the real GitHub. The suite is registered only when RR_CLI_LIVE_TESTS is set.
 *
 * New RoadRunner releases must not break these tests: they pin a release line that is complete,
 * or check what holds for any release.
 */
#[Test]
final class GitHubTest
{
    /**
     * Passed through to `rr`: a token lifts the rate limit, and a shared DLoad cache spares the requests
     * for release pages already seen.
     */
    private const PASSED_ENV = ['GITHUB_TOKEN', 'DLOAD_CACHE_DIR'];

    private Workdir $dir;

    #[BeforeTest]
    public function prepare(): void
    {
        $this->dir = new Workdir();
    }

    #[AfterTest]
    public function cleanup(): void
    {
        $this->dir->remove();
    }

    #[DataSet(['linux', 'amd64', 'rr'])]
    #[DataSet(['windows', 'amd64', 'rr.exe'])]
    public function downloadsRealBinary(string $os, string $arch, string $binary): void
    {
        $result = $this->rr()->run(['get', '--filter=2025.*', "--os=$os", "--arch=$arch", '--no-config']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::int((int) \filesize($this->dir->file($binary)))->greaterThan(1_000_000);
    }

    public function downloadsSemverReleaseByDefault(): void
    {
        $result = $this->rr()->run(['get', '--os=linux', '--arch=amd64', '--no-config']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::int((int) \filesize($this->dir->file('rr')))->greaterThan(1_000_000);
        Assert::same(\preg_match('/\brr \(v?(\d+)\.\d+\.\d+[^)]*\) has been installed/', $result->output(), $match), 1, (string) $result);
        // Calendar versions (2023.x-2025.x) are older than 3.x and must lose to it
        Assert::int((int) $match[1])->greaterThanOrEqual(3)->lessThan(2000);
    }

    public function listsReleasesWithCompatibility(): void
    {
        $result = $this->rr()->run(['versions', '--os=linux', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->stdout)
            ->matchesRegex('/^\s*3\.\d+\.\d+\s+stable\s+✓\s+\(\d+\)\s+✓\s*$/mu')
            ->matchesRegex('/^\s*2025\.1\.15\s+stable\s+.*\(reason: incompatible version\)/mu');
    }

    private function rr(): Rr
    {
        $rr = new Rr($this->dir->path, null);
        foreach (self::PASSED_ENV as $name) {
            $value = \getenv($name);
            if (\is_string($value) && $value !== '') {
                $rr = $rr->withEnv($name, $value);
            }
        }

        return $rr;
    }
}
