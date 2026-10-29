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
     * Looks for ext-protobuf, then for google/protobuf in the project, then in the Composer installation running this tool.
     *
     * The extension goes first: once loaded it defines the `Google\Protobuf` classes, so the package is never autoloaded.
     * The project is read from its own `installed.php`: run from a global installation or a PHAR,
     * {@see InstalledVersions} knows only the packages installed next to this tool,
     * so it is asked only when the project has no `installed.php`.
     *
     * @param string $projectDir Root of the project, see {@see self::projectDir()}.
     */
    public static function detect(string $projectDir): ?self
    {
        return self::fromExtension() ?? (self::installedFile($projectDir) === null
            ? self::fromInstalledVersions()
            : self::fromProject($projectDir));
    }

    /**
     * Finds the project root as Composer does: the nearest directory up from the working one with a `composer.json`
     * (or the file named by `COMPOSER`), looking neither in the user home nor in the filesystem root.
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

    public static function fromProject(string $projectDir): ?self
    {
        $file = self::installedFile($projectDir);
        if ($file === null) {
            return null;
        }

        /**
         * @psalm-suppress UnresolvableInclude
         * @var mixed $installed
         */
        $installed = (static fn(string $file): mixed => require $file)($file);
        /** @var mixed $package */
        $package = \is_array($installed) && \is_array($installed['versions'] ?? null)
            ? $installed['versions'][self::PACKAGE] ?? null
            : null;
        /** @var mixed $version */
        $version = \is_array($package) ? $package['pretty_version'] ?? null : null;

        return \is_string($version) ? new self(self::PACKAGE, $version) : null;
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
            $minor > 20 => \sprintf('^%d.0', $minor),
            $major === 3 => \sprintf('~3.%d.0', $minor),
            default => null,
        };
    }

    /**
     * @return string|null The project's `installed.php`, null when there is none.
     */
    private static function installedFile(string $projectDir): ?string
    {
        $file = self::vendorDir($projectDir) . '/composer/installed.php';

        return \is_file($file) ? $file : null;
    }

    /**
     * Resolves `vendor-dir` as Composer does, expanding a leading `~/`; `{$home}` (the Composer home) is left as is.
     */
    private static function vendorDir(string $projectDir): string
    {
        $vendor = Environment::get('COMPOSER_VENDOR_DIR');

        if ($vendor === null || $vendor === '') {
            $file = self::composerFile();
            $file = self::isAbsolute($file) ? $file : $projectDir . '/' . $file;
            /** @var mixed $composer */
            $composer = \json_decode((string) @\file_get_contents($file), true);
            /** @var mixed $vendor */
            $vendor = \is_array($composer) ? $composer['config']['vendor-dir'] ?? null : null;
            $vendor = \is_string($vendor) && $vendor !== '' ? $vendor : 'vendor';
        }

        $user = self::userDir();
        if ($user !== null && \preg_match('#^~[/\\\\]#', $vendor) === 1) {
            $vendor = $user . \substr($vendor, 1);
        }

        return self::isAbsolute($vendor) ? $vendor : $projectDir . '/' . $vendor;
    }

    /**
     * @return non-empty-string The manifest named by `COMPOSER`, relative to the project unless absolute.
     */
    private static function composerFile(): string
    {
        $file = \trim(Environment::get('COMPOSER') ?? '');

        return $file === '' ? 'composer.json' : $file;
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
