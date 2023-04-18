<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\RoadRunner\Console\Environment\OperatingSystem\Factory;

/**
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
     * @param array|null $variables
     * @return OperatingSystemType
     */
    #[ExpectedValues(valuesFromClass: OperatingSystem::class)]
    public static function createFromGlobals(array $variables = null): string
    {
        return (new Factory())->createFromGlobals();
    }


    public static function isValid(string $value): bool
    {
        return \in_array($value, self::all(), true);
    }

    /**
     * @return array<string, OperatingSystemType>
     */
    public static function all(): array
    {
        static $values;

        if ($values === null) {
            $values = Enum::values(self::class, 'OS_');
        }

        /** @psalm-var array<string, OperatingSystemType> $values */
        return $values;
    }
}
