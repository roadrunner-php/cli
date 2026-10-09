<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\Command;
use Spiral\RoadRunner\Console\Repository\RepositoriesCollection;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Testo\Assert;
use Testo\Test;

#[Test]
final class CommandTest
{
    public function repositoryAggregatesRoadRunnerReleases(): void
    {
        $command = new class('test') extends Command {
            public function repository(): RepositoryInterface
            {
                return $this->getRepository();
            }
        };

        $repository = $command->repository();

        Assert::instanceOf($repository, RepositoriesCollection::class);
        Assert::same($repository->getName(), 'unknown/unknown');
    }
}
