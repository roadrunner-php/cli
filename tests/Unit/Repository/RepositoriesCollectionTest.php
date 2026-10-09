<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository;

use Spiral\RoadRunner\Console\Repository\RepositoriesCollection;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\InMemoryRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Testo\Assert;
use Testo\Skip;
use Testo\Test;

#[Test]
final class RepositoriesCollectionTest
{
    public function hasNoName(): void
    {
        Assert::same((new RepositoriesCollection([]))->getName(), 'unknown/unknown');
    }

    public function returnsReleasesOfSingleRepository(): void
    {
        $collection = new RepositoriesCollection([
            new InMemoryRepository(Releases::release('v2024.1.0'), Releases::release('v2024.2.0')),
        ]);

        Assert::same(Releases::versions($collection->getReleases()), ['v2024.1.0', 'v2024.2.0']);
    }

    #[Skip('Bug: getReleases() collects the generator with preserved keys, so releases of a later repository overwrite earlier ones with the same index')]
    public function mergesReleasesOfAllRepositories(): void
    {
        $collection = new RepositoriesCollection([
            new InMemoryRepository(Releases::release('v2024.1.0')),
            new InMemoryRepository(Releases::release('v2.12.3')),
        ]);

        Assert::same(Releases::versions($collection->getReleases()), ['v2024.1.0', 'v2.12.3']);
    }
}
