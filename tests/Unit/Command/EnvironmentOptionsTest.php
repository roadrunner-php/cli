<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Command;

use Spiral\RoadRunner\Console\Command\ArchitectureOption;
use Spiral\RoadRunner\Console\Command\OperatingSystemOption;
use Spiral\RoadRunner\Console\Command\StabilityOption;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\OptionHost;
use Testo\Assert;
use Testo\Test;

/**
 * Options for the target environment: they accept any value and warn about unknown ones.
 */
#[Test]
final class EnvironmentOptionsTest
{
    public function stabilityDefaultsToStable(): void
    {
        $host = new OptionHost();
        $option = new StabilityOption($host->command);
        $input = $host->input();

        Assert::same($option->getName(), 'stability');
        Assert::same($host->command->getDefinition()->getOption('stability')->getShortcut(), 's');
        Assert::same($option->get($input, $host->io($input)), 'stable');
        Assert::same($host->display(), '');
    }

    public function stabilityAcceptsKnownValue(): void
    {
        $host = new OptionHost();
        $option = new StabilityOption($host->command);
        $input = $host->input(['--stability' => 'beta']);

        Assert::same($option->get($input, $host->io($input)), 'beta');
        Assert::same($host->display(), '');
    }

    public function stabilityWarnsAboutUnknownValue(): void
    {
        $host = new OptionHost();
        $option = new StabilityOption($host->command);
        $input = $host->input(['--stability' => 'nightly']);

        Assert::same($option->get($input, $host->io($input)), 'nightly');
        Assert::string($host->display())
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('Possibly invalid stability (--stability=nightly) option (available: stable, RC, beta, alpha, dev)');
    }

    public function operatingSystemWarnsAboutUnknownValue(): void
    {
        $host = new OptionHost();
        $option = new OperatingSystemOption($host->command);
        $input = $host->input(['--os' => 'macos']);

        Assert::same($option->get($input, $host->io($input)), 'macos');
        Assert::string($host->display())
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('Possibly invalid operating system (--os=macos) option');
    }

    public function operatingSystemAcceptsKnownValue(): void
    {
        $host = new OptionHost();
        $option = new OperatingSystemOption($host->command, 'system', 'y');
        $input = $host->input(['--system' => 'darwin']);

        Assert::same($option->getName(), 'system');
        Assert::same($option->get($input, $host->io($input)), 'darwin');
        Assert::same($host->display(), '');
    }

    public function architectureWarnsAboutUnknownValue(): void
    {
        $host = new OptionHost();
        $option = new ArchitectureOption($host->command);
        $input = $host->input(['--arch' => 'x86_64']);

        Assert::same($option->get($input, $host->io($input)), 'x86_64');
        Assert::string($host->display())
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('Possibly invalid architecture (--arch=x86_64) option (available: amd64, arm64)');
    }

    public function architectureAcceptsShortcut(): void
    {
        $host = new OptionHost();
        $option = new ArchitectureOption($host->command);
        $input = $host->input(['-a' => 'arm64']);

        Assert::same($option->get($input, $host->io($input)), 'arm64');
    }
}
