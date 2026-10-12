<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Environment;

/**
 * @internal
 */
final class Environment
{
    /**
     * @return string|null The variable from `$_ENV`, then from `$_SERVER`; null when it is not a string in either.
     */
    public static function get(string $key): ?string
    {
        /** @var mixed $result */
        $result = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        return \is_string($result) ? $result : null;
    }
}
