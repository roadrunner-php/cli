<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\DownloadProtocBinaryCommand;
use Spiral\RoadRunner\Console\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\DLoadGetSpy;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class DownloadProtocBinaryCommandTest
{
    private string $dir;

    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
    }

    #[AfterTest]
    public function removeDirectory(): void
    {
        TempDirectory::remove($this->dir);
    }

    public function describesItself(): void
    {
        $command = new DownloadProtocBinaryCommand();

        Assert::same($command->getName(), 'download-protoc-binary');
        Assert::same($command->getDescription(), 'Install or update protoc-gen-php-grpc binary');
    }

    public function definesEnvironmentOptions(): void
    {
        $definition = (new DownloadProtocBinaryCommand())->getDefinition();

        Assert::array(\array_keys($definition->getOptions()))
            ->sameElementsAs(['os', 'arch', 'filter', 'location', 'stability']);
        Assert::same($definition->getOption('stability')->getDefault(), 'stable');
    }

    #[DataSet([0])]
    #[DataSet([1], 'failure')]
    public function installsBinaryThroughDLoad(int $exitCode): void
    {
        $get = new DLoadGetSpy(exitCode: $exitCode, binary: 'protoc-gen-php-grpc');
        $tester = $this->tester($get);

        $status = $tester->execute($this->input(), ['interactive' => false]);

        Assert::same($status, $exitCode);
        Assert::count($get->calls, 1);
        Assert::same($get->calls[0]->getArgument('software'), ['protoc-gen-php-grpc:2025.1.*@stable']);
        Assert::same($get->calls[0]->getOption('path'), $this->dir);
        Assert::same($get->calls[0]->getOption('os'), 'darwin');
        Assert::same($get->calls[0]->getOption('arch'), 'arm64');
        Assert::string($tester->getDisplay())
            ->contains('Version:          2025.1.*')
            ->contains('Operating System: darwin')
            ->contains('Architecture:     arm64');
    }

    #[DataSet(['linux', 'protoc-gen-php-grpc'])]
    #[DataSet(['windows', 'protoc-gen-php-grpc.exe'])]
    public function keepsExistingBinaryUnlessConfirmed(string $os, string $binary): void
    {
        \file_put_contents($this->dir . '/' . $binary, 'old binary');
        $get = new DLoadGetSpy();
        $tester = $this->tester($get);

        $status = $tester->execute($this->input(['--os' => $os]), ['interactive' => false]);

        Assert::same($status, 1);
        Assert::same($get->calls, []);
        Assert::same(\file_get_contents($this->dir . '/' . $binary), 'old binary');
        Assert::string($tester->getDisplay())
            ->contains('protoc-gen-php-grpc binary file already exists!')
            ->contains('Skipping protoc-gen-php-grpc installation...')
            ->contains('protoc-gen-php-grpc has not been installed');
    }

    public function overwritesExistingBinaryWhenConfirmed(): void
    {
        \file_put_contents($this->dir . '/protoc-gen-php-grpc', 'old binary');
        $get = new DLoadGetSpy(binary: 'protoc-gen-php-grpc');
        $tester = $this->tester($get);
        $tester->setInputs(['yes']);

        $status = $tester->execute($this->input(['--os' => 'linux']));

        Assert::same($status, 0);
        Assert::true($get->calls[0]->getOption('force'));
        Assert::same(\file_get_contents($this->dir . '/protoc-gen-php-grpc'), 'new binary');
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function input(array $input = []): array
    {
        return $input + [
            '--location' => $this->dir,
            '--os' => 'darwin',
            '--arch' => 'arm64',
            '--filter' => '2025.1.*',
        ];
    }

    private function tester(DLoadGetSpy $get): CommandTester
    {
        return new CommandTester(new DownloadProtocBinaryCommand(downloader: new DLoadDownloader($get)));
    }
}
