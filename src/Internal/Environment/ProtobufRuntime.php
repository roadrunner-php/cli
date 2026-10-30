<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Environment;

use Composer\InstalledVersions;

/**
 * The protobuf runtime a project generates code for: the google/protobuf package or ext-protobuf.
 *
 * @internal
 */
final class ProtobufRuntime
{
    public const PACKAGE = 'google/protobuf';
    public const EXTENSION = 'ext-protobuf';

    /**
     * @param non-empty-string $name {@see self::PACKAGE} or {@see self::EXTENSION}.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {}

    /**
     * Looks for ext-protobuf, then for google/protobuf in the project, or in the Composer installation running this tool
     * when there is no project.
     *
     * The extension goes first: once loaded it defines the `Google\Protobuf` classes, so the package is never autoloaded.
     * The package is still returned after it, for the project may run on a PHP without the extension.
     *
     * @param string $projectDir Root of the project, see {@see self::projectDir()}.
     * @return list<self> Every runtime found, the one in use first.
     */
    public static function detect(string $projectDir): array
    {
        return \array_values(\array_filter([self::fromExtension(), self::fromPackage($projectDir)]));
    }

    /**
     * Finds the project root as Composer does: the nearest directory up from the working one with a `composer.json`
     * (or the file named by `COMPOSER`), looking neither in the user home nor in the filesystem root.
     *
     * An absolute `COMPOSER` keeps the working directory: Composer resolves `vendor-dir` against it,
     * not against the manifest's directory.
     *
     * @return string The working directory itself when none has the file.
     */
    public static function projectDir(string $workingDir): string
    {
        $file = self::composerFile();
        if (self::isAbsolute($file)) {
            return $workingDir;
        }

        $home = \realpath(self::userDir() ?? '/');
        for ($dir = $workingDir; \dirname($dir) !== $dir && \realpath($dir) !== $home; $dir = \dirname($dir)) {
            if (\is_file($dir . '/' . $file)) {
                return $dir;
            }
        }

        return $workingDir;
    }

    /**
     * Looks for google/protobuf in the project's `installed.json`, or in the Composer installation running this tool
     * when there is no project manifest at all.
     *
     * Run from a global installation or a PHAR, {@see InstalledVersions} knows only the packages installed next
     * to this tool, so a project with its own manifest is never matched against them, even before `composer install`.
     * The file is decoded rather than `installed.php` included: the project may be any directory above the working one.
     */
    public static function fromPackage(string $projectDir): ?self
    {
        $file = self::vendorDir($projectDir) . '/composer/installed.json';

        return match (true) {
            \is_file($file) => self::fromInstalledFile($file),
            \is_file(self::manifest($projectDir)) => null,
            default => self::fromInstalledVersions(),
        };
    }

    public static function fromInstalledVersions(): ?self
    {
        if (! InstalledVersions::isInstalled(self::PACKAGE)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion(self::PACKAGE);

        return $version === null ? null : new self(self::PACKAGE, $version);
    }

    public static function fromExtension(): ?self
    {
        $version = \phpversion('protobuf');

        return $version === false ? null : new self(self::EXTENSION, $version);
    }

    /**
     * The protoc versions whose generated code this runtime supports: the same protoc major.
     *
     * Since protobuf 21 the PHP runtime minor is the protoc major (3.21.x and protoc 21.x, 4.33.x and 33.x,
     * 5.36.x and 36.x), and the runtime major is bumped on its own. Up to 3.20 both shared one version.
     * The constraint also checks an installed protoc, and protoc 21.x reports itself as `libprotoc 3.21.x`.
     *
     * @return non-empty-string|null Null when the version is not a release number, e.g. a branch.
     */
    public function protocConstraint(): ?string
    {
        if (\preg_match('/^v?(\d+)\.(\d+)/', \trim($this->version), $m) !== 1) {
            return null;
        }

        [, $major, $minor] = \array_map(\intval(...), $m);

        return match (true) {
            $minor === 21 => '^21.0 || ~3.21.0',
            $minor > 20 => \sprintf('^%d.0', $minor),
            $major === 3 => \sprintf('~3.%d.0', $minor),
            default => null,
        };
    }

    /**
     * Reads both the Composer 2 `{"packages": [...]}` layout and the Composer 1 bare list of packages.
     */
    private static function fromInstalledFile(string $file): ?self
    {
        /** @var mixed $installed */
        $installed = \json_decode((string) @\file_get_contents($file), true);
        /** @var mixed $packages */
        $packages = \is_array($installed) ? $installed['packages'] ?? $installed : null;

        /** @var mixed $package */
        foreach (\is_array($packages) ? $packages : [] as $package) {
            if (\is_array($package) && ($package['name'] ?? null) === self::PACKAGE) {
                /** @var mixed $version */
                $version = $package['version'] ?? null;

                return \is_string($version) ? new self(self::PACKAGE, $version) : null;
            }
        }

        return null;
    }

    /**
     * Resolves `vendor-dir` as Composer does; `{$home}` (the Composer home) is left as is.
     */
    private static function vendorDir(string $projectDir): string
    {
        $vendor = Environment::get('COMPOSER_VENDOR_DIR');

        if ($vendor === null || $vendor === '') {
            /** @var mixed $composer */
            $composer = \json_decode((string) @\file_get_contents(self::manifest($projectDir)), true);
            /** @var mixed $vendor */
            $vendor = \is_array($composer) ? $composer['config']['vendor-dir'] ?? null : null;
            $vendor = \is_string($vendor) && $vendor !== '' ? $vendor : 'vendor';
        }

        $vendor = self::expandPath($vendor);

        return self::isAbsolute($vendor) ? $vendor : $projectDir . '/' . $vendor;
    }

    /**
     * Expands a leading `~/`, `$VAR` or `%VAR%` (on any OS) as Composer's `Platform::expandPath()` does;
     * `${VAR}` is left as is, and an unset variable expands to an empty string.
     */
    private static function expandPath(string $path): string
    {
        if (\preg_match('#^~[/\\\\]#', $path) === 1) {
            $user = self::userDir();

            return $user === null ? $path : $user . \substr($path, 1);
        }

        if (\preg_match('#^(\$|(%))(\w++)(?(2)%)(.*)#', $path, $m) !== 1) {
            return $path;
        }

        $value = $m[3] === 'HOME' && \PHP_OS_FAMILY === 'Windows' ? self::userDir() : Environment::get($m[3]);

        return ($value ?? '') . $m[4];
    }

    /**
     * @return non-empty-string The manifest named by `COMPOSER`, relative to the project unless absolute.
     */
    private static function composerFile(): string
    {
        $file = \trim(Environment::get('COMPOSER') ?? '');

        return $file === '' ? 'composer.json' : $file;
    }

    private static function manifest(string $projectDir): string
    {
        $file = self::composerFile();

        return self::isAbsolute($file) ? $file : $projectDir . '/' . $file;
    }

    private static function userDir(): ?string
    {
        $dir = Environment::get('HOME');
        $dir = $dir === null || $dir === '' ? Environment::get('USERPROFILE') : $dir;

        return $dir === null || $dir === '' ? null : $dir;
    }

    private static function isAbsolute(string $path): bool
    {
        return \preg_match('#^(?:[a-z]:)?[/\\\\]#i', $path) === 1;
    }
}
