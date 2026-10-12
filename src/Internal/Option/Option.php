<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Option;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * A string option of a console command with a default value.
 *
 * @internal
 */
abstract class Option
{
    public function __construct(
        Command $command,
        protected readonly string $name,
        string $short,
    ) {
        $command->addOption($name, $short, InputOption::VALUE_OPTIONAL, $this->getDescription(), $this->default());
    }

    public function get(InputInterface $input, StyleInterface $io): string
    {
        /** @var mixed $value */
        $value = $input->getOption($this->name);

        return \is_string($value) && $value !== '' ? $value : $this->default();
    }

    abstract protected function getDescription(): string;

    abstract protected function default(): string;
}
