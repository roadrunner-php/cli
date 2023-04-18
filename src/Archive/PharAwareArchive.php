<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

abstract class PharAwareArchive extends Archive
{
    protected \PharData $archive;

    public function __construct(\SplFileInfo $archive)
    {
        parent::__construct($archive);

        $this->archive = $this->open($archive);
    }

    abstract protected function open(\SplFileInfo $file): \PharData;

    /**
     * @param iterable<string, string> $mappings
     * @return \Generator<mixed, \SplFileInfo>
     */
    public function extract(iterable $mappings): \Generator
    {
        $phar = $this->open($this->archive);

        if (! $phar->isReadable()) {
            throw new \LogicException(\sprintf('Could not open "%s" for reading', $this->archive->getPathname()));
        }

        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            foreach ($mappings as $from => $to) {
                if ($file->getFilename() === $from && (yield $file => new \SplFileInfo($to)) !== false) {
                    \copy($file->getPathname(), $to);
                }
            }
        }
    }
}
