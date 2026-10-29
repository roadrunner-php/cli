<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Environment\OperatingSystem;
use Spiral\RoadRunner\Console\Internal\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Internal\Installation\ExistingBinary;
use Spiral\RoadRunner\Console\Internal\Option\ArchitectureOption;
use Spiral\RoadRunner\Console\Internal\Option\InstallationLocationOption;
use Spiral\RoadRunner\Console\Internal\Option\OperatingSystemOption;
use Spiral\RoadRunner\Console\Internal\Option\StabilityOption;
use Spiral\RoadRunner\Console\Internal\Option\VersionFilterOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * protoc-gen-php-grpc file download command.
 *
 * @psalm-api
 */
final class DownloadProtocBinaryCommand extends Command
{
    private OperatingSystemOption $os;
    private ArchitectureOption $arch;
    private VersionFilterOption $version;
    private StabilityOption $stability;
    private InstallationLocationOption $location;
    private DLoadDownloader $downloader;

    public function __construct(?string $name = null, ?DLoadDownloader $downloader = null)
    {
        $this->downloader = $downloader ?? new DLoadDownloader();
        parent::__construct($name ?? 'download-protoc-binary');

        $this->os = new OperatingSystemOption($this);
        $this->arch = new ArchitectureOption($this);
        $this->version = new VersionFilterOption($this);
        $this->location = new InstallationLocationOption($this);
        $this->stability = new StabilityOption($this);
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Install or update protoc-gen-php-grpc binary (deprecated, use get-grpc-tools)';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->warning('download-protoc-binary is deprecated: use get-grpc-tools, which also installs protoc and buf.');

        $target = $this->location->get($input, $io);
        $os = $this->os->get($input, $io);

        $output->writeln('');
        $output->writeln(' Environment:');
        $output->writeln(\sprintf('   - Version:          <info>%s</info>', $this->version->get($input, $io)));
        $output->writeln(\sprintf('   - Stability:        <info>%s</info>', $this->stability->get($input, $io)));
        $output->writeln(\sprintf('   - Operating System: <info>%s</info>', $os));
        $output->writeln(\sprintf('   - Architecture:     <info>%s</info>', $this->arch->get($input, $io)));
        $output->writeln('');

        $binary = $target . ($os === OperatingSystem::OS_WINDOWS ? '/protoc-gen-php-grpc.exe' : '/protoc-gen-php-grpc');

        if (!ExistingBinary::mayInstall($binary, 'protoc-gen-php-grpc', $io)) {
            $io->warning('protoc-gen-php-grpc has not been installed');

            return 1;
        }

        return $this->downloader->download(
            software: 'protoc-gen-php-grpc',
            constraint: $this->version->get($input, $io),
            stability: $this->stability->get($input, $io),
            os: $os,
            arch: $this->arch->get($input, $io),
            location: $target,
            force: true,
            output: $output,
        );
    }
}
