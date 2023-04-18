<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

final class PharArchive extends PharAwareArchive
{
    protected function open(\SplFileInfo $file): \PharData
    {
        return new \PharData($file->getPathname());
    }
}
