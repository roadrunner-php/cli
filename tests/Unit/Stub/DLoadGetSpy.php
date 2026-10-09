<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Stub;

use Internal\DLoad\Command\Get;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Stands in for DLoad's `get` command: accepts exactly its input definition, records the input
 * and writes a fake binary into `--path` instead of downloading.
 */
final class DLoadGetSpy extends Command
{
    /** @var list<InputInterface> */
    public array $calls = [];

    public function __construct(
        private readonly int $exitCode = self::SUCCESS,
        private readonly string $binary = 'rr',
    ) {
        parent::__construct('get');
    }

    protected function configure(): void
    {
        $this->setDefinition((new Get())->getDefinition());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->calls[] = $input;

        if ($this->exitCode === self::SUCCESS) {
            \file_put_contents($input->getOption('path') . '/' . $this->binary, 'new binary');
        }

        $output->writeln('dload: ' . \implode(' ', $input->getArgument('software')));

        return $this->exitCode;
    }
}
