<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

use Composer\InstalledVersions;

final class RoadRunnerVersion
{
    public const PACKAGE_NAME = 'spiral/roadrunner';

    /**
     * Used when the spiral/roadrunner metapackage is not installed.
     */
    public const DEFAULT_CONSTRAINT = '3.*';

    /**
     * Returns the RoadRunner binary version constraint matching the installed
     * spiral/roadrunner metapackage, e.g. "3.*" or "2025.*".
     *
     * Not delegated to {@see \Spiral\RoadRunner\Version::constraint()}: without the metapackage
     * it falls back to the roadrunner/worker version, whose major is unrelated to the binary's.
     */
    public static function constraint(): string
    {
        if (!InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return self::DEFAULT_CONSTRAINT;
        }

        $version = \ltrim((string) InstalledVersions::getPrettyVersion(self::PACKAGE_NAME), 'v');
        [$major] = \explode('.', $version);

        return \str_contains($version, '.') && \is_numeric($major)
            ? $major . '.*'
            : self::DEFAULT_CONSTRAINT;
    }
}
