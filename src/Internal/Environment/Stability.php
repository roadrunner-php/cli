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
 * Release stabilities as composer/semver names them.
 *
 * @internal
 * @psalm-type StabilityType = Stability::STABILITY_*
 */
final class Stability
{
    public const STABILITY_STABLE = 'stable';
    public const STABILITY_RC = 'RC';
    public const STABILITY_BETA = 'beta';
    public const STABILITY_ALPHA = 'alpha';
    public const STABILITY_DEV = 'dev';
    private const WEIGHT = [
        self::STABILITY_STABLE => 4,
        self::STABILITY_RC => 3,
        self::STABILITY_BETA => 2,
        self::STABILITY_ALPHA => 1,
        self::STABILITY_DEV => 0,
    ];

    /**
     * @param StabilityType|string $type Unknown stabilities weigh as "dev".
     * @return int<0, 4>
     */
    public static function toInt(string $type): int
    {
        return self::WEIGHT[$type] ?? 0;
    }

    /**
     * @return list<StabilityType> From the most to the least stable.
     */
    public static function all(): array
    {
        return \array_keys(self::WEIGHT);
    }

    public static function isValid(string $value): bool
    {
        return isset(self::WEIGHT[$value]);
    }
}
