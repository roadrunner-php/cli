<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Stub;

final class TempDirectory
{
    public static function create(): string
    {
        $path = \sys_get_temp_dir() . '/rr-cli-test-' . \bin2hex(\random_bytes(6));
        \mkdir($path, 0777, true);

        return \str_replace('\\', '/', $path);
    }

    public static function remove(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @\rmdir($item->getPathname()) : @\unlink($item->getPathname());
        }

        @\rmdir($path);
    }

    /**
     * Creates an archive with the given files and returns its path.
     *
     * @param non-empty-string $name File name; ".zip", ".tar.gz" and ".tar" are supported.
     * @param array<non-empty-string, string> $files
     */
    public static function archive(string $directory, string $name, array $files): string
    {
        $path = $directory . '/' . $name;

        if (\str_ends_with($name, '.tar.gz')) {
            // Gzip by hand: an archive produced by PharData::compress() stays cached in the phar
            // registry, and PHP 8.2/8.3 then read its entries back as empty files.
            $tar = $directory . '/' . \bin2hex(\random_bytes(4)) . '.tar';
            self::fill(new \PharData($tar), $files);
            \file_put_contents($path, \gzencode((string) \file_get_contents($tar)));
            \unlink($tar);

            return $path;
        }

        self::fill(new \PharData($path), $files);

        return $path;
    }

    /**
     * @param array<non-empty-string, string> $files
     */
    private static function fill(\PharData $archive, array $files): void
    {
        foreach ($files as $file => $content) {
            $archive->addFromString($file, $content);
        }
    }
}
