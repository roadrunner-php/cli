<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

interface RepositoryInterface
{
    public function getName(): string;

    /**
     * @return ReleasesCollection|iterable<ReleaseInterface>
     */
    public function getReleases(): ReleasesCollection;
}
