<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

interface ArchiveInterface
{
    /**
     * @param iterable<string, string> $mappings
     * @return \Generator<mixed, \SplFileInfo>
     */
    public function extract(iterable $mappings): \Generator;
}
