<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Command;

use Spiral\RoadRunner\Console\Environment\OperatingSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\StyleInterface;

class OperatingSystemOption extends Option
{
    public function __construct(Command $command, string $name = 'os', string $short = 'o')
    {
        parent::__construct($command, $name, $short);
    }

    #[\Override]
    public function get(InputInterface $input, StyleInterface $io): string
    {
        $os = parent::get($input, $io);

        if (! OperatingSystem::isValid($os)) {
            $message = 'Possibly invalid operating system (--%s=%s) option (available: %s)';
            $io->warning(\sprintf($message, $this->name, $os, $this->choices()));
        }

        return $os;
    }

    #[\Override]
    protected function getDescription(): string
    {
        return 'Required operating system (one of: ' . $this->choices() . ')';
    }

    #[\Override]
    protected function default(): string
    {
        return OperatingSystem::createFromGlobals();
    }

    private function choices(): string
    {
        return \implode(', ', OperatingSystem::all());
    }
}
