<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Stub;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A bare console command to register options on, with input and output bound to its definition.
 */
final class OptionHost
{
    public readonly Command $command;
    public readonly BufferedOutput $output;

    public function __construct()
    {
        $this->command = new Command('test');
        $this->output = new BufferedOutput();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function input(array $parameters = []): InputInterface
    {
        $input = new ArrayInput($parameters, $this->command->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    public function io(InputInterface $input): SymfonyStyle
    {
        return new SymfonyStyle($input, $this->output);
    }

    public function display(): string
    {
        return $this->output->fetch();
    }
}
