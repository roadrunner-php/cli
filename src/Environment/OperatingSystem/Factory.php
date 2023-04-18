<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment\OperatingSystem;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\RoadRunner\Console\Environment\OperatingSystem;

/**
 * @internal Factory is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\RoadRunner\Console\Environment
 *
 * @psalm-import-type OperatingSystemType from OperatingSystem
 */
class Factory
{
    private const ERROR_UNKNOWN_OS = 'Current OS (%s) may not be supported';

    /**
     * @return OperatingSystemType
     */
    #[ExpectedValues(valuesFromClass: OperatingSystem::class)]
    public function createFromGlobals(): string
    {
        return match (\PHP_OS_FAMILY) {
            'Windows' => OperatingSystem::OS_WINDOWS,
            'BSD' => OperatingSystem::OS_BSD,
            'Darwin' => OperatingSystem::OS_DARWIN,
            'Linux' => \str_contains(\PHP_OS, 'Alpine')
                ? OperatingSystem::OS_ALPINE
                : OperatingSystem::OS_LINUX,
            default => throw new \OutOfRangeException(\sprintf(self::ERROR_UNKNOWN_OS, \PHP_OS_FAMILY)),
        };
    }
}
