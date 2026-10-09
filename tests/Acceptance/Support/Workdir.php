<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * An empty temporary directory to run `rr` in.
 */
final class Workdir
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = \sys_get_temp_dir() . '/rr-cli-acceptance/' . \uniqid('run-', true);
        \mkdir($this->path, 0777, true);
    }

    public function file(string $name): string
    {
        return $this->path . '/' . $name;
    }

    public function has(string $name): bool
    {
        return \is_file($this->file($name));
    }

    public function read(string $name): string
    {
        return (string) \file_get_contents($this->file($name));
    }

    public function write(string $name, string $content): void
    {
        \file_put_contents($this->file($name), $content);
    }

    /**
     * Top-level sections of the generated `.rr.yaml`.
     *
     * @return list<string>
     */
    public function configSections(): array
    {
        return \array_keys((array) Yaml::parse($this->read('.rr.yaml')));
    }

    /**
     * Files left in the directory, the DLoad cache aside.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return \array_values(\array_diff(\scandir($this->path), ['.', '..', '.dload-cache']));
    }

    public function remove(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @\rmdir($file->getPathname()) : @\unlink($file->getPathname());
        }

        @\rmdir($this->path);
    }
}
