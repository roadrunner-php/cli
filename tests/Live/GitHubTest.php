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
 * `rr get` against the real GitHub. The suite is registered only when RR_CLI_LIVE_TESTS is set.
 */
#[Test]
final class GitHubTest
{
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
        $rr = new Rr($this->dir->path, null);
        $token = \getenv('GITHUB_TOKEN');
        if (\is_string($token) && $token !== '') {
            $rr = $rr->withEnv('GITHUB_TOKEN', $token);
        }

        $result = $rr->run(['get', '--filter=2025.*', "--os=$os", "--arch=$arch", '--no-config']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::int((int) \filesize($this->dir->file($binary)))->greaterThan(1_000_000);
    }
}
