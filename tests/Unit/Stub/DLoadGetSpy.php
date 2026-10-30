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

    /** @var list<string> Contents of the `--config` file at the time of each call. */
    public array $configs = [];

    /**
     * @param string|null $binary File name of the fake binary; the requested software name by default.
     * @param list<string> $failing Software names that fail with exit code 1 whatever `$exitCode` is.
     */
    public function __construct(
        private readonly int $exitCode = self::SUCCESS,
        private readonly ?string $binary = null,
        private readonly array $failing = [],
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
        $this->configs[] = (string) @\file_get_contents((string) $input->getOption('config'));

        $software = \implode(' ', $input->getArgument('software'));
        $name = \explode(':', $software, 2)[0];
        $exitCode = \in_array($name, $this->failing, true) ? self::FAILURE : $this->exitCode;

        if ($exitCode === self::SUCCESS) {
            \file_put_contents($input->getOption('path') . '/' . ($this->binary ?? $name), 'new binary');
        }

        $output->writeln('dload: ' . $software);

        return $exitCode;
    }
}
