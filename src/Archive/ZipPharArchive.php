<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

final class ZipPharArchive extends PharAwareArchive
{
    protected function open(\SplFileInfo $file): \PharData
    {
        $format = \Phar::ZIP | \Phar::GZ;

        return new \PharData($file->getPathname(), 0, null, $format);
    }
}
