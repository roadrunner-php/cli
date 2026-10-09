<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Command;

use Spiral\RoadRunner\Console\Command\InstallationLocationOption;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\OptionHost;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class InstallationLocationOptionTest
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

    public function defaultsToWorkingDirectory(): void
    {
        $host = new OptionHost();
        $option = new InstallationLocationOption($host->command);
        $input = $host->input();

        Assert::same($option->get($input, $host->io($input)), \getcwd());
    }

    public function acceptsWritableDirectory(): void
    {
        $host = new OptionHost();
        $option = new InstallationLocationOption($host->command);
        $input = $host->input(['--location' => $this->dir]);

        Assert::same($option->get($input, $host->io($input)), $this->dir);
    }

    public function rejectsMissingDirectory(): never
    {
        $host = new OptionHost();
        $option = new InstallationLocationOption($host->command);
        $input = $host->input(['-l' => $this->dir . '/missing']);

        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Installation directory not found or not writable');

        $option->get($input, $host->io($input));
    }

    public function rejectsFile(): never
    {
        \file_put_contents($this->dir . '/file', '');
        $host = new OptionHost();
        $option = new InstallationLocationOption($host->command);
        $input = $host->input(['--location' => $this->dir . '/file']);

        Expect::exception(\InvalidArgumentException::class);

        $option->get($input, $host->io($input));
    }
}
