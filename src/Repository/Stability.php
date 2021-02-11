<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

/**
 * @psalm-type StabilityType = Stability::STABILITY_*
 */
final class Stability
{
    /**
     * @var string
     */
    public const STABILITY_STABLE = 'stable';

    /**
     * @var string
     */
    public const STABILITY_RC = 'RC';

    /**
     * @var string
     */
    public const STABILITY_BETA = 'beta';

    /**
     * @var string
     */
    public const STABILITY_ALPHA = 'alpha';

    /**
     * @var string
     */
    public const STABILITY_DEV = 'dev';
}
