<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Environment\OperatingSystem;
use Spiral\RoadRunner\Console\Internal\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Internal\Environment\ProtobufRuntime;
use Spiral\RoadRunner\Console\Internal\Environment\RoadRunnerVersion;
use Spiral\RoadRunner\Console\Internal\Installation\ExistingBinary;
use Spiral\RoadRunner\Console\Internal\Option\ArchitectureOption;
use Spiral\RoadRunner\Console\Internal\Option\InstallationLocationOption;
use Spiral\RoadRunner\Console\Internal\Option\OperatingSystemOption;
use Spiral\RoadRunner\Console\Internal\Option\StabilityOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Downloads the binaries `buf generate` needs to generate gRPC code: buf, protoc and protoc-gen-php-grpc.
 *
 * @psalm-api
 */
final class GetGrpcToolsCommand extends Command
{
    private const BUF_CONSTRAINT = '^1';
    private const ANY_VERSION = '*';

    private OperatingSystemOption $os;
    private ArchitectureOption $arch;
    private StabilityOption $stability;
    private InstallationLocationOption $location;
    private DLoadDownloader $downloader;

    /** @var \Closure(): ?ProtobufRuntime */
    private \Closure $runtime;

    /**
     * @param (\Closure(): ?ProtobufRuntime)|null $runtime Finds the protobuf runtime of the project;
     *        {@see ProtobufRuntime::detect()} in the working directory by default.
     */
    public function __construct(?string $name = null, ?DLoadDownloader $downloader = null, ?\Closure $runtime = null)
    {
        $this->downloader = $downloader ?? new DLoadDownloader();
        $this->runtime = $runtime ?? static fn(): ?ProtobufRuntime => ProtobufRuntime::detect(\getcwd() ?: '.');
        parent::__construct($name ?? 'get-grpc-tools');

        $this->os = new OperatingSystemOption($this);
        $this->arch = new ArchitectureOption($this);
        $this->location = new InstallationLocationOption($this);
        $this->stability = new StabilityOption($this);
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Install or update buf, protoc and protoc-gen-php-grpc to generate gRPC code';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $target = $this->location->get($input, $io);
        $os = $this->os->get($input, $io);
        $arch = $this->arch->get($input, $io);
        $stability = $this->stability->get($input, $io);

        $output->writeln('');
        $output->writeln(' Environment:');
        $output->writeln(\sprintf('   - Stability:        <info>%s</info>', $stability));
        $output->writeln(\sprintf('   - Operating System: <info>%s</info>', $os));
        $output->writeln(\sprintf('   - Architecture:     <info>%s</info>', $arch));
        $output->writeln('');

        [$protoc, $derivedFrom] = $this->protocConstraint($input, $io);

        $tools = [
            'buf' => self::option($input, 'buf') ?? self::ANY_VERSION,
            'protoc' => $protoc,
            'protoc-gen-php-grpc' => self::option($input, 'plugin') ?? self::ANY_VERSION,
        ];

        $output->writeln(' Versions:');
        foreach ($tools as $tool => $constraint) {
            $output->writeln(\sprintf('   - %-20s <info>%s</info>', $tool . ':', $constraint));
        }
        $output->writeln('');

        $failed = [];
        foreach ($tools as $tool => $constraint) {
            $binary = $target . '/' . $tool . ($os === OperatingSystem::OS_WINDOWS ? '.exe' : '');
            if (! ExistingBinary::mayInstall($binary, $tool, $io)) {
                continue;
            }

            $code = $this->downloader->download(
                software: $tool,
                constraint: $constraint,
                stability: $stability,
                os: $os,
                arch: $arch,
                location: $target,
                force: true,
                output: $output,
            );

            if ($code === self::SUCCESS) {
                continue;
            }

            $failed[] = $tool;
            if ($tool === 'protoc' && $derivedFrom !== null) {
                $io->error(\sprintf(
                    'Could not install protoc %s, the version %s %s supports. '
                    . 'If no protoc release matches it, choose one with --protoc=<constraint>.',
                    $constraint,
                    $derivedFrom->name,
                    $derivedFrom->version,
                ));
            }
        }

        if ($failed !== []) {
            $io->error('Not installed: ' . \implode(', ', $failed));

            return self::FAILURE;
        }

        $io->success('gRPC tools are ready in ' . $target);

        return self::SUCCESS;
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'protoc',
            null,
            InputOption::VALUE_REQUIRED,
            'Version constraint of protoc; by default the protoc major the installed google/protobuf or ext-protobuf supports',
        );

        $this->addOption(
            'plugin',
            null,
            InputOption::VALUE_REQUIRED,
            'Version constraint of protoc-gen-php-grpc',
            RoadRunnerVersion::constraint(),
        );

        $this->addOption(
            'buf',
            null,
            InputOption::VALUE_REQUIRED,
            'Version constraint of buf',
            self::BUF_CONSTRAINT,
        );
    }

    /**
     * @return non-empty-string|null
     */
    private static function option(InputInterface $input, string $name): ?string
    {
        /** @var mixed $value */
        $value = $input->getOption($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{non-empty-string, ProtobufRuntime|null} The constraint and the runtime it is derived from.
     */
    private function protocConstraint(InputInterface $input, SymfonyStyle $io): array
    {
        $option = self::option($input, 'protoc');
        if ($option !== null) {
            return [$option, null];
        }

        $runtime = ($this->runtime)();
        if ($runtime === null) {
            $io->warning(\sprintf(
                'Neither %s nor %s is installed: installing the latest protoc. Choose a version with --protoc=<constraint>.',
                ProtobufRuntime::PACKAGE,
                ProtobufRuntime::EXTENSION,
            ));

            return [self::ANY_VERSION, null];
        }

        $constraint = $runtime->protocConstraint();
        if ($constraint === null) {
            $io->warning(\sprintf(
                'Can not tell the protoc version for %s %s: installing the latest protoc. Choose a version with --protoc=<constraint>.',
                $runtime->name,
                $runtime->version,
            ));

            return [self::ANY_VERSION, null];
        }

        $io->writeln(\sprintf(' %s <info>%s</info> -> protoc <info>%s</info>', $runtime->name, $runtime->version, $constraint));
        $io->newLine();

        return [$constraint, $runtime];
    }
}
