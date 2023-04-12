<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

class RepositoriesCollection implements RepositoryInterface
{
    /**
     * @param array<RepositoryInterface> $repositories
     */
    public function __construct(
        private readonly array $repositories,
    ) {
    }

    public function getName(): string
    {
        return 'unknown/unknown';
    }

    public function getReleases(): ReleasesCollection
    {
        return ReleasesCollection::from(function () {
            foreach ($this->repositories as $repository) {
                yield from $repository->getReleases();
            }
        });
    }
}
