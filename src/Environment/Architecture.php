<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\RoadRunner\Console\Environment\Architecture\Factory;

/**
 * @psalm-type ArchitectureType = Architecture::ARCH_*
 */
final class Architecture
{
    public const ARCH_X86_64 = 'amd64';
    public const ARCH_ARM_64 = 'arm64';

    /**
     * @return ArchitectureType
     */
    #[ExpectedValues(valuesFromClass: Architecture::class)]
    public static function createFromGlobals(): string
    {
        return (new Factory())->createFromGlobals();
    }

    /**
     * @return array<string, ArchitectureType>
     */
    public static function all(): array
    {
        static $values;

        if ($values === null) {
            $values = Enum::values(self::class, 'ARCH_');
        }

        /** @psalm-var array<string, ArchitectureType> $values */
        return $values;
    }

    public static function isValid(string $value): bool
    {
        return \in_array($value, self::all(), true);
    }
}
