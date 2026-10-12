<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Command;

use Spiral\RoadRunner\Console\Command\VersionFilterOption;
use Spiral\RoadRunner\Console\Environment\RoadRunnerVersion;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\OptionHost;
use Testo\Assert;
use Testo\Test;

#[Test]
final class VersionFilterOptionTest
{
    public function defaultsToInstalledRoadRunnerConstraint(): void
    {
        $host = new OptionHost();
        $option = new VersionFilterOption($host->command);
        $input = $host->input();

        Assert::same($option->get($input, $host->io($input)), RoadRunnerVersion::constraint());
    }

    public function acceptsShortcut(): void
    {
        $host = new OptionHost();
        $option = new VersionFilterOption($host->command);
        $input = $host->input(['-f' => '^3.1']);

        Assert::same($option->getName(), 'filter');
        Assert::same($option->get($input, $host->io($input)), '^3.1');
    }
}
