<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

final class Environment
{
    /**
     * @param string|null $default
     *
     * @psalm-return ($default is string ? string : string|null)
     */
    public static function get(string $key, string $default = null, array $variables = []): ?string
    {
        /** @var mixed $result */
        $result = $variables[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if (\is_string($result)) {
            return $result;
        }

        return $default;
    }
}
