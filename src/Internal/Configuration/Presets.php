<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration;

use Spiral\RoadRunner\Console\Internal\Configuration\Section\Http;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Jobs;

/**
 * @internal
 */
final class Presets
{
    public const WEB_PRESET_NANE = 'web';
    public const WEB_PLUGINS = [
        Http::class,
        Jobs::class,
    ];
}
