<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @psalm-type InputOptionType = InputOption::VALUE_*
 */
abstract class Option implements OptionInterface
{
    protected string $name;

    public function __construct(Command $command, string $name, ?string $short = null)
    {
        $this->name = $name;

        $this->register($command, $name, $short ?? $name);
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function get(InputInterface $input, StyleInterface $io): string
    {
        return self::toString($input->getOption($this->name) ?: $this->default());
    }

    /**
     * @return InputOptionType
     */
    protected function getMode(): int
    {
        return InputOption::VALUE_OPTIONAL;
    }

    abstract protected function getDescription(): string;

    abstract protected function default(): ?string;

    private static function toString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private function register(Command $command, string $name, string $short): void
    {
        $command->addOption($name, $short, $this->getMode(), $this->getDescription(), $this->default());
    }
}
