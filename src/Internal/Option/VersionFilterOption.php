<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Option;

use Spiral\RoadRunner\Console\Internal\Environment\RoadRunnerVersion;
use Symfony\Component\Console\Command\Command;

/**
 * @internal
 */
final class VersionFilterOption extends Option
{
    private readonly string $default;

    /**
     * @param string|null $default The constraint of the installed RoadRunner by default.
     */
    public function __construct(
        Command $command,
        string $name = 'filter',
        string $short = 'f',
        ?string $default = null,
    ) {
        $this->default = $default ?? RoadRunnerVersion::constraint();
        parent::__construct($command, $name, $short);
    }

    #[\Override]
    protected function getDescription(): string
    {
        return 'Required version of RoadRunner binaries';
    }

    #[\Override]
    protected function default(): string
    {
        return $this->default;
    }
}
