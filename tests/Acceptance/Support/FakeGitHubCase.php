<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;

/**
 * Runs the fake GitHub around a test case and gives every test an empty working directory.
 */
trait FakeGitHubCase
{
    private Workdir $dir;

    #[BeforeClass]
    public static function startServer(): void
    {
        FakeGitHub::start();
    }

    #[AfterClass]
    public static function stopServer(): void
    {
        FakeGitHub::stop();
    }

    #[BeforeTest]
    public function prepare(): void
    {
        $this->dir = new Workdir();
        FakeGitHub::resetRequests();
    }

    #[AfterTest]
    public function cleanup(): void
    {
        $this->dir->remove();
    }

    private function rr(?string $apiUrl = null): Rr
    {
        return new Rr($this->dir->path, $apiUrl ?? FakeGitHub::url());
    }
}
