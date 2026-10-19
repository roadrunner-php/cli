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
 * @internal
 */
final class RoadRunnerVersion
{
    /**
     * The metapackage was renamed without `replace`, so older installations still carry the old name.
     */
    private const PACKAGE_NAMES = ['roadrunner/roadrunner', 'spiral/roadrunner'];

    /**
     * Used when the RoadRunner metapackage is not installed.
     */
    private const DEFAULT_CONSTRAINT = '3.*';

    /**
     * Returns the RoadRunner binary version constraint matching the installed
     * RoadRunner metapackage, e.g. "3.*" or "2025.*".
     *
     * Not delegated to {@see \Spiral\RoadRunner\Version::constraint()}: without the metapackage
     * it falls back to the roadrunner/worker version, whose major is unrelated to the binary's.
     */
    public static function constraint(): string
    {
        foreach (self::PACKAGE_NAMES as $name) {
            if (InstalledVersions::isInstalled($name)) {
                return self::fromVersion((string) InstalledVersions::getPrettyVersion($name));
            }
        }

        return self::DEFAULT_CONSTRAINT;
    }

    private static function fromVersion(string $version): string
    {
        $version = \ltrim($version, 'v');
        [$major] = \explode('.', $version);

        return \str_contains($version, '.') && \is_numeric($major)
            ? $major . '.*'
            : self::DEFAULT_CONSTRAINT;
    }
}
