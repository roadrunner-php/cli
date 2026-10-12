<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Operating systems in the names of the RoadRunner release assets.
 *
 * @psalm-type OperatingSystemType = OperatingSystem::OS_*
 */
final class OperatingSystem
{
    public const OS_DARWIN = 'darwin';
    public const OS_BSD = 'freebsd';
    public const OS_LINUX = 'linux';
    public const OS_WINDOWS = 'windows';
    public const OS_ALPINE = 'unknown-musl';

    /**
     * @return OperatingSystemType
     * @throws \OutOfRangeException When the current operating system is none of the known ones.
     */
    #[ExpectedValues(valuesFromClass: OperatingSystem::class)]
    public static function createFromGlobals(): string
    {
        return match (\PHP_OS_FAMILY) {
            'Windows' => self::OS_WINDOWS,
            'BSD' => self::OS_BSD,
            'Darwin' => self::OS_DARWIN,
            'Linux' => \str_contains(\PHP_OS, 'Alpine') ? self::OS_ALPINE : self::OS_LINUX,
            default => throw new \OutOfRangeException(\sprintf('Current OS (%s) may not be supported', \PHP_OS_FAMILY)),
        };
    }

    /**
     * @return array<non-empty-string, OperatingSystemType> Operating systems by constant name.
     */
    public static function all(): array
    {
        return [
            'OS_DARWIN' => self::OS_DARWIN,
            'OS_BSD' => self::OS_BSD,
            'OS_LINUX' => self::OS_LINUX,
            'OS_WINDOWS' => self::OS_WINDOWS,
            'OS_ALPINE' => self::OS_ALPINE,
        ];
    }

    public static function isValid(string $value): bool
    {
        return \in_array($value, self::all(), true);
    }
}
