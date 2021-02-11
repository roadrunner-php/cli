<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Environment\Architecture;

use JetBrains\PhpStorm\ExpectedValues;
use Spiral\RoadRunner\Console\Environment\Architecture;
use Spiral\RoadRunner\Console\Environment\EnvironmentAwareTrait;

/**
 * @internal Factory is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\RoadRunner\Console\Environment
 *
 * @psalm-import-type ArchitectureType from Architecture
 */
class Factory
{
    use EnvironmentAwareTrait;

    /**
     * @var string
     */
    public const ENV_PROCESSOR_ARCHITECTURE = 'PROCESSOR_ARCHITECTURE';

    /**
     * @var string
     */
    private const ERROR_UNKNOWN_ARCH = 'Current architecture (%s) may not be supported';

    /**
     * @var array<string, array<string>>
     */
    private const UNAME_MAPPINGS = [
        Architecture::ARCH_X86_64 => [
            'AMD64',
            'x86',
            'x64',
            'x86_64',
        ],
    ];

    /**
     * @param array|null $variables
     * @return ArchitectureType
     */
    #[ExpectedValues(valuesFromClass: Architecture::class)]
    public function createFromGlobals(array $variables = null): string
    {
        $uname = $this->env($variables, self::ENV_PROCESSOR_ARCHITECTURE, \php_uname('m'));

        foreach (self::UNAME_MAPPINGS as $result => $available) {
            if (\in_array($uname, $available, true)) {
                return $result;
            }
        }

        throw new \OutOfRangeException(
            \sprintf(self::ERROR_UNKNOWN_ARCH, $uname)
        );
    }
}
