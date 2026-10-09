<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Stub;

use Spiral\RoadRunner\Console\Repository\ReleaseInterface;
use Spiral\RoadRunner\Console\Repository\ReleasesCollection;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;

final class InMemoryRepository implements RepositoryInterface
{
    /** @var list<ReleaseInterface> */
    private array $releases;

    public function __construct(
        ReleaseInterface ...$releases,
    ) {
        $this->releases = \array_values($releases);
    }

    public function getName(): string
    {
        return 'roadrunner-server/roadrunner';
    }

    public function getReleases(): ReleasesCollection
    {
        return new ReleasesCollection($this->releases);
    }
}
