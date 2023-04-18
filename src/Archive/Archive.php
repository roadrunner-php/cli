<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

abstract class Archive implements ArchiveInterface
{
    public function __construct(\SplFileInfo $archive)
    {
        $this->assertArchiveValid($archive);
    }

    private function assertArchiveValid(\SplFileInfo $archive): void
    {
        if (! $archive->isFile()) {
            throw new \InvalidArgumentException(
                \sprintf('Archive "%s" is not a file', $archive->getFilename())
            );
        }

        if (! $archive->isReadable()) {
            throw new \InvalidArgumentException(
                \sprintf('Archive file "%s" is not readable', $archive->getFilename())
            );
        }
    }
}
