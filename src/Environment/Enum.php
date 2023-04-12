<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

/**
 * @internal Enum is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\RoadRunner\Console\Environment
 */
final class Enum
{
    /**
     * @param class-string $class
     * @return array<string, string|int>
     */
    public static function values(string $class, string $prefix): array
    {
        $result = [];

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return [];
        }

        /** @psalm-var int|string $value */
        foreach ($reflection->getConstants() as $name => $value) {
            if (\str_starts_with($name, $prefix)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
