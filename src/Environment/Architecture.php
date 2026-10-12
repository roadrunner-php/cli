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
 * Processor architectures in the names of the RoadRunner release assets.
 *
 * @psalm-type ArchitectureType = Architecture::ARCH_*
 */
final class Architecture
{
    public const ARCH_X86_64 = 'amd64';
    public const ARCH_ARM_64 = 'arm64';

    /**
     * Values of `php_uname('m')` by architecture.
     */
    private const UNAME_MAPPINGS = [
        self::ARCH_X86_64 => ['AMD64', 'amd64', 'x86', 'x64', 'x86_64'],
        self::ARCH_ARM_64 => ['arm64', 'aarch64'],
    ];

    /**
     * @return ArchitectureType
     * @throws \OutOfRangeException When the current architecture is none of the known ones.
     */
    #[ExpectedValues(valuesFromClass: Architecture::class)]
    public static function createFromGlobals(): string
    {
        $uname = \php_uname('m');

        foreach (self::UNAME_MAPPINGS as $result => $available) {
            if (\in_array($uname, $available, true)) {
                return $result;
            }
        }

        throw new \OutOfRangeException(\sprintf('Current architecture (%s) may not be supported', $uname));
    }

    /**
     * @return array<non-empty-string, ArchitectureType> Architectures by constant name.
     */
    public static function all(): array
    {
        return [
            'ARCH_X86_64' => self::ARCH_X86_64,
            'ARCH_ARM_64' => self::ARCH_ARM_64,
        ];
    }

    public static function isValid(string $value): bool
    {
        return \in_array($value, self::all(), true);
    }
}
