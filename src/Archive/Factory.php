<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Archive;

use Spiral\RoadRunner\Console\Repository\AssetInterface;

/**
 * @psalm-import-type ArchiveMatcher from FactoryInterface
 */
class Factory implements FactoryInterface
{
    /**
     * @var array<ArchiveMatcher>
     */
    private array $matchers = [];

    public function __construct()
    {
        $this->bootDefaultMatchers();
    }

    private function bootDefaultMatchers(): void
    {
        $this->extend($this->matcher('zip',
            static fn (\SplFileInfo $info): ArchiveInterface => new ZipPharArchive($info)
        ));

        $this->extend($this->matcher('tar.gz',
            static fn (\SplFileInfo $info): ArchiveInterface => new TarPharArchive($info)
        ));

        $this->extend($this->matcher('phar',
            static fn (\SplFileInfo $info): ArchiveInterface => new PharArchive($info)
        ));
    }

    /**
     * @param ArchiveMatcher $then
     * @return ArchiveMatcher
     */
    private function matcher(string $extension, \Closure $then): \Closure
    {
        return static fn (\SplFileInfo $info): ?ArchiveInterface =>
            \str_ends_with(\strtolower($info->getFilename()), '.' . $extension) ? $then($info) : null
        ;
    }

    public function extend(\Closure $matcher): self
    {
        \array_unshift($this->matchers, $matcher);

        return $this;
    }

    public function create(\SplFileInfo $file): ArchiveInterface
    {
        $errors = [];

        foreach ($this->matchers as $matcher) {
            try {
                if ($archive = $matcher($file)) {
                    return $archive;
                }
            } catch (\Throwable $e) {
                $errors[] = '  - ' . $e->getMessage();
                continue;
            }
        }

        $error = \sprintf('Can not open the archive "%s":%s', $file->getFilename(), \PHP_EOL) .
            \implode(\PHP_EOL, $errors)
        ;

        throw new \InvalidArgumentException($error);
    }

    public function fromAsset(AssetInterface $asset, \Closure $progress = null, string $temp = null): ArchiveInterface
    {
        $temp = $this->getTempDirectory($temp) . '/' . $asset->getName();

        $file = new \SplFileObject($temp, 'wb+');

        try {
            foreach ($asset->download($progress) as $chunk) {
                $file->fwrite($chunk);
            }
        } catch (\Throwable $e) {
            @\unlink($temp);

            throw $e;
        }

        return $this->create($file);
    }

    private function getTempDirectory(?string $temp): string
    {
        if ($temp) {
            if (! \is_dir($temp) || ! \is_writable($temp)) {
                throw new \LogicException(\sprintf('Directory "%s" is not writeable', $temp));
            }

            return $temp;
        }

        return \sys_get_temp_dir();
    }
}
