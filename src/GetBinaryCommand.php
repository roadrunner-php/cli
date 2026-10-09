<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Command\ArchitectureOption;
use Spiral\RoadRunner\Console\Command\InstallationLocationOption;
use Spiral\RoadRunner\Console\Command\OperatingSystemOption;
use Spiral\RoadRunner\Console\Command\StabilityOption;
use Spiral\RoadRunner\Console\Command\VersionFilterOption;
use Spiral\RoadRunner\Console\Configuration\Generator;
use Spiral\RoadRunner\Console\Configuration\Plugins;
use Spiral\RoadRunner\Console\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Environment\OperatingSystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

class GetBinaryCommand extends Command
{
    private OperatingSystemOption $os;
    private ArchitectureOption $arch;
    private VersionFilterOption $version;
    private StabilityOption $stability;
    private InstallationLocationOption $location;

    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'get-binary');

        $this->os = new OperatingSystemOption($this);
        $this->arch = new ArchitectureOption($this);
        $this->version = new VersionFilterOption($this);
        $this->location = new InstallationLocationOption($this);
        $this->stability = new StabilityOption($this);
    }

    public function getDescription(): string
    {
        return 'Install or update RoadRunner binary';
    }

    /**
     *
     * @throws \Throwable
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io($input, $output);

        $target = $this->location->get($input, $io);
        $os = $this->os->get($input, $io);

        $output->writeln('');
        $output->writeln(' Environment:');
        $output->writeln(\sprintf('   - Version:          <info>%s</info>', $this->version->get($input, $io)));
        $output->writeln(\sprintf('   - Stability:        <info>%s</info>', $this->stability->get($input, $io)));
        $output->writeln(\sprintf('   - Operating System: <info>%s</info>', $os));
        $output->writeln(\sprintf('   - Architecture:     <info>%s</info>', $this->arch->get($input, $io)));
        $output->writeln('');

        $binary = $target . ($os === OperatingSystem::OS_WINDOWS ? '/rr.exe' : '/rr');
        $installed = false;

        if ($this->checkExisting($binary, $io)) {
            $code = (new DLoadDownloader())->download(
                software: 'rr',
                constraint: $this->version->get($input, $io),
                stability: $this->stability->get($input, $io),
                os: $os,
                arch: $this->arch->get($input, $io),
                location: $target,
                force: true,
                output: $output,
            );

            if ($code !== self::SUCCESS) {
                return $code;
            }

            $installed = true;
        }

        $this->installConfig($target, $input, $io);

        if (! $installed) {
            $io->warning('RoadRunner has not been installed');

            return 1;
        }

        $io->success('Your project is now ready in ' . $target);

        $io->title('Whats Next?');
        $io->listing([
            // 1)
            'For more detailed documentation, see the ' .
            '<info><href=https://roadrunner.dev>https://roadrunner.dev</></info>',

            // 2)
            'To run the application, use the following command: ' .
            '<comment>$ ' . \basename($binary) . ' serve</comment>',
        ]);

        return 0;
    }

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

        $this->addOption(
            'no-config',
            null,
            InputOption::VALUE_NONE,
            'Do not generate configuration at all.',
        );
    }

    /**
     * @throws \Throwable
     */
    private function installConfig(string $to, InputInterface $in, StyleInterface $io): bool
    {
        $to .= '/.rr.yaml';

        if (\is_file($to) || \is_file(\getcwd() . '/.rr.yaml')) {
            return false;
        }

        if ($in->getOption('no-config') || ! $io->confirm('Do you want create default ".rr.yaml" configuration file?', true)) {
            return false;
        }

        $generator = new Generator();
        $plugins = $in->getOption('preset') ?
            Plugins::fromPreset($in->getOption('preset')) :
            Plugins::fromPlugins($in->getOption('plugin'));

        try {
            $config = $generator->generate($plugins);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
        }

        \file_put_contents($to, $config);

        return true;
    }

    private function checkExisting(string $binary, StyleInterface $io): bool
    {
        if (\is_file($binary)) {
            $io->warning('RoadRunner binary file already exists!');

            if (! $io->confirm('Do you want overwrite it?', false)) {
                $io->note('Skipping RoadRunner installation...');

                return false;
            }
        }

        return true;
    }
}
