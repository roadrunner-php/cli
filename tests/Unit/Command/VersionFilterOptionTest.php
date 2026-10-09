<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Command;

use Spiral\RoadRunner\Console\Command\VersionFilterOption;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\InMemoryRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\OptionHost;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Spiral\RoadRunner\Version;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class VersionFilterOptionTest
{
    public function defaultsToInstalledRoadRunnerConstraint(): void
    {
        $host = new OptionHost();
        $option = new VersionFilterOption($host->command);
        $input = $host->input();

        Assert::same($option->get($input, $host->io($input)), Version::constraint());
    }

    public function findReturnsMatchingReleasesNewestFirst(): void
    {
        $host = new OptionHost();
        $option = new VersionFilterOption($host->command);
        $input = $host->input(['--filter' => '^2024.1']);
        $repository = new InMemoryRepository(
            Releases::release('v2024.1.0'),
            Releases::release('v2023.3.12'),
            Releases::release('v2024.2.1'),
        );

        $releases = $option->find($input, $host->io($input), $repository);

        Assert::same(Releases::versions($releases), ['v2024.2.1', 'v2024.1.0']);
    }

    public function findFailsWhenNothingMatches(): never
    {
        $host = new OptionHost();
        $option = new VersionFilterOption($host->command);
        $input = $host->input(['-f' => '^2025.1']);
        $repository = new InMemoryRepository(Releases::release('v2023.3.12'), Releases::release('v2024.1.0'));

        Expect::exception(\UnexpectedValueException::class)
            ->withMessage(
                "Could not find any available RoadRunner binary version which meets version criterion (--filter=^2025.1)\n"
                . 'Available: v2024.1.0, v2023.3.12',
            );

        $option->find($input, $host->io($input), $repository);
    }

    public function choicesListsUniqueVersions(): void
    {
        $option = new VersionFilterOption((new OptionHost())->command);

        Assert::same(
            $option->choices(Releases::collection('v2024.1.0', 'v2023.3.12', 'v2024.1.0')),
            'v2024.1.0, v2023.3.12',
        );
    }
}
