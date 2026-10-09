<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

$suites = [
    new SuiteConfig(
        name: 'Unit',
        location: ['tests/Unit'],
    ),
    new SuiteConfig(
        name: 'Acceptance',
        location: ['tests/Acceptance'],
    ),
];

# Downloads real RoadRunner releases from GitHub, so it runs only on request
if (\filter_var(\getenv('RR_CLI_LIVE_TESTS') ?: '0', \FILTER_VALIDATE_BOOLEAN)) {
    $suites[] = new SuiteConfig(
        name: 'Live',
        location: ['tests/Live'],
    );
}

return new ApplicationConfig(
    src: ['src'],
    suites: $suites,
);
