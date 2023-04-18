<?php

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
    public function __construct(Command $command, protected string $name, string $short = null)
    {
        $this->register($command, $name, $short ?? $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function register(Command $command, string $name, string $short): void
    {
        $command->addOption($name, $short, $this->getMode(), $this->getDescription(), $this->default());
    }

    /**
     * @return InputOptionType
     */
    protected function getMode(): int
    {
        return InputOption::VALUE_OPTIONAL;
    }

    abstract protected function getDescription(): string;

    public function get(InputInterface $input, StyleInterface $io): string
    {
        $result = $input->getOption($this->name) ?: $this->default();

        return \is_string($result) ? $result : '';
    }

    abstract protected function default(): ?string;
}
