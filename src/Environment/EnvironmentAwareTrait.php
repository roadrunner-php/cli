<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment;

trait EnvironmentAwareTrait
{
    /**
     * @param array|null $variables
     * @param string $key
     * @param string|null $default
     * @return string|null
     *
     * @psalm-return ($default is string ? string : string|null)
     */
    protected function env(?array $variables, string $key, string $default = null): ?string
    {
        /** @var mixed $result */
        $result = $variables[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if (\is_string($result)) {
            return $result;
        }

        return $default;
    }
}
