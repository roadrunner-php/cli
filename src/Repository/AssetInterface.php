<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

interface AssetInterface
{
    public function getName(): string;

    public function getUri(): string;

    /**
     * @param \Closure|null $progress
     * @return iterable<mixed, string>
     */
    public function download(\Closure $progress = null): iterable;
}
