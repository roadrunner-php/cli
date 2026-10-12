<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Internal\Configuration\Generator;
use Spiral\RoadRunner\Console\Internal\Configuration\Plugins;
use Spiral\RoadRunner\Console\Internal\Option\InstallationLocationOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MakeConfigCommand extends Command
{
    private InstallationLocationOption $location;

    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'make-config');
        $this->location = new InstallationLocationOption($this);
    }

    /**
     *
     * @throws \Throwable
     */
    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        $target = $this->location->get($input, $io) . '/.rr.yaml';

        if (\is_file($target) || \is_file((string) \getcwd() . '/.rr.yaml')) {
            return self::FAILURE;
        }

        $generator = new Generator();
        /** @var string|null $preset */
        $preset = $input->getOption('preset');
        /** @var string[] $names */
        $names = $input->getOption('plugin');
        $plugins = $preset !== null && $preset !== '' ? Plugins::fromPreset($preset) : Plugins::fromPlugins($names);

        try {
            $config = $generator->generate($plugins);
            \file_put_contents($target, $config);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'plugin',
            'p',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Generate configuration with selected plugins.',
        );

        $this->addOption(
            'preset',
            null,
            InputOption::VALUE_OPTIONAL,
            'Generate configuration with plugins in a selected preset.',
        );
    }
}
