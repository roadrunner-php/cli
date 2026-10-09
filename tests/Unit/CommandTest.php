<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\Command;
use Spiral\RoadRunner\Console\Repository\RepositoriesCollection;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class CommandTest
{
    private const ENV_API_URL = 'RR_GITHUB_API_URL';

    public function repositoryAggregatesRoadRunnerReleases(): void
    {
        $repository = self::repository();

        Assert::instanceOf($repository, RepositoriesCollection::class);
        Assert::same($repository->getName(), 'unknown/unknown');
    }

    public function repositoryRequestsApiUrlFromEnvironment(): void
    {
        // The unsupported scheme makes the client fail before any connection, with the URL in the message.
        $_SERVER[self::ENV_API_URL] = 'ftp://github.mock/api';

        try {
            Expect::exception(\InvalidArgumentException::class)
                ->withMessageContaining('ftp://github.mock/api/repos/roadrunner-server/roadrunner/releases');

            self::repository()->getReleases()->empty();
        } finally {
            unset($_SERVER[self::ENV_API_URL]);
        }
    }

    private static function repository(): RepositoryInterface
    {
        $command = new class('test') extends Command {
            public function repository(): RepositoryInterface
            {
                return $this->getRepository();
            }
        };

        return $command->repository();
    }
}
