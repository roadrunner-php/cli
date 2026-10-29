<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\GetGrpcToolsCommand;
use Spiral\RoadRunner\Console\Internal\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\Internal\Environment\ProtobufRuntime;
use Spiral\RoadRunner\Console\Internal\Environment\RoadRunnerVersion;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\DLoadGetSpy;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class GetGrpcToolsCommandTest
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
        $command = new GetGrpcToolsCommand();

        Assert::same($command->getName(), 'get-grpc-tools');
        Assert::string($command->getDescription())->contains('buf')->contains('protoc')->contains('protoc-gen-php-grpc');
    }

    public function definesOptions(): void
    {
        $definition = (new GetGrpcToolsCommand())->getDefinition();

        Assert::array(\array_keys($definition->getOptions()))
            ->sameElementsAs(['os', 'arch', 'location', 'stability', 'protoc', 'plugin', 'buf']);
        Assert::same($definition->getOption('buf')->getDefault(), '^1');
        Assert::same($definition->getOption('plugin')->getDefault(), '3.*');
        Assert::null($definition->getOption('protoc')->getDefault());
    }

    public function installsAllToolsThroughDLoad(): void
    {
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::PACKAGE, '5.36.2'));

        $status = $tester->execute($this->input(), ['interactive' => false]);

        Assert::same($status, 0, $tester->getDisplay());
        Assert::same(
            \array_map(static fn($input): array => $input->getArgument('software'), $get->calls),
            [['buf:^1@stable'], ['protoc:^36.0@stable'], ['protoc-gen-php-grpc:3.*@stable']],
        );
        foreach ($get->calls as $call) {
            Assert::same($call->getOption('path'), $this->dir);
            Assert::same($call->getOption('os'), 'darwin');
            Assert::same($call->getOption('arch'), 'arm64');
            Assert::true($call->getOption('force'));
        }
        Assert::string($tester->getDisplay())
            ->contains('google/protobuf 5.36.2 -> protoc ^36.0')
            ->contains('protoc:              ^36.0')
            ->contains('gRPC tools are ready');
    }

    public function derivesProtocFromExtension(): void
    {
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::EXTENSION, '4.33.1'));

        $tester->execute($this->input(), ['interactive' => false]);

        Assert::same($get->calls[1]->getArgument('software'), ['protoc:^33.0@stable']);
        Assert::string($tester->getDisplay())->contains('ext-protobuf 4.33.1 -> protoc ^33.0');
    }

    public function takesVersionsFromOptions(): void
    {
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, static fn(): never => throw new \LogicException('Must not detect the runtime'));

        $status = $tester->execute($this->input([
            '--protoc' => '^30.0',
            '--plugin' => '3.0.*',
            '--buf' => '1.50.*',
            '--stability' => 'beta',
        ]), ['interactive' => false]);

        Assert::same($status, 0, $tester->getDisplay());
        Assert::same(
            \array_map(static fn($input): array => $input->getArgument('software'), $get->calls),
            [['buf:1.50.*@beta'], ['protoc:^30.0@beta'], ['protoc-gen-php-grpc:3.0.*@beta']],
        );
    }

    public function fallsBackToDefaultsForEmptyOptions(): void
    {
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::PACKAGE, '5.36.2'));

        $status = $tester->execute($this->input([
            '--protoc' => '',
            '--plugin' => '',
            '--buf' => '',
        ]), ['interactive' => false]);

        Assert::same($status, 0, $tester->getDisplay());
        Assert::same(
            \array_map(static fn($input): array => $input->getArgument('software'), $get->calls),
            [['buf:^1@stable'], ['protoc:^36.0@stable'], [\sprintf('protoc-gen-php-grpc:%s@stable', RoadRunnerVersion::constraint())]],
        );
    }

    #[DataSet([null, 'Neither google/protobuf nor ext-protobuf is installed'], 'no runtime')]
    #[DataSet(['dev-main', 'Can not tell the protoc version for google/protobuf dev-main'], 'branch')]
    public function installsLatestProtocWithWarning(?string $version, string $warning): void
    {
        $get = new DLoadGetSpy();
        $runtime = $version === null ? null : new ProtobufRuntime(ProtobufRuntime::PACKAGE, $version);
        $tester = $this->tester($get, $runtime);

        $status = $tester->execute($this->input(), ['interactive' => false]);

        Assert::same($status, 0, $tester->getDisplay());
        Assert::same($get->calls[1]->getArgument('software'), ['protoc:*@stable']);
        Assert::string($this->display($tester))->contains($warning)->contains('--protoc=<constraint>');
    }

    public function suggestsProtocOptionWhenDerivedVersionFails(): void
    {
        $get = new DLoadGetSpy(failing: ['protoc']);
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::PACKAGE, '5.37.0'));

        $status = $tester->execute($this->input(), ['interactive' => false]);

        Assert::same($status, 1);
        Assert::count($get->calls, 3);
        Assert::string($this->display($tester))
            ->contains('Could not install protoc ^37.0, the version google/protobuf 5.37.0 supports')
            ->contains('--protoc=<constraint>')
            ->contains('Not installed: protoc');
        Assert::true(\is_file($this->dir . '/buf'));
        Assert::true(\is_file($this->dir . '/protoc-gen-php-grpc'));
    }

    public function reportsFailureWithoutHintForExplicitVersion(): void
    {
        $get = new DLoadGetSpy(failing: ['buf', 'protoc']);
        $tester = $this->tester($get, null);

        $status = $tester->execute($this->input(['--protoc' => '^99.0']), ['interactive' => false]);

        Assert::same($status, 1);
        Assert::string($this->display($tester))
            ->notContains('Could not install protoc')
            ->contains('Not installed: buf, protoc');
    }

    #[DataSet(['linux', ''])]
    #[DataSet(['windows', '.exe'])]
    public function keepsExistingBinariesUnlessConfirmed(string $os, string $extension): void
    {
        \file_put_contents($this->dir . '/protoc' . $extension, 'old binary');
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::PACKAGE, '4.33.1'));

        $status = $tester->execute($this->input(['--os' => $os]), ['interactive' => false]);

        Assert::same($status, 0, $tester->getDisplay());
        Assert::same(
            \array_map(static fn($input): array => $input->getArgument('software'), $get->calls),
            [['buf:^1@stable'], ['protoc-gen-php-grpc:3.*@stable']],
        );
        Assert::same(\file_get_contents($this->dir . '/protoc' . $extension), 'old binary');
        Assert::string($tester->getDisplay())
            ->contains('protoc binary file already exists!')
            ->contains('Skipping protoc installation...');
    }

    public function overwritesExistingBinaryWhenConfirmed(): void
    {
        \file_put_contents($this->dir . '/buf', 'old binary');
        $get = new DLoadGetSpy();
        $tester = $this->tester($get, new ProtobufRuntime(ProtobufRuntime::PACKAGE, '4.33.1'));
        $tester->setInputs(['yes']);

        $status = $tester->execute($this->input(['--os' => 'linux']));

        Assert::same($status, 0, $tester->getDisplay());
        Assert::count($get->calls, 3);
        Assert::same(\file_get_contents($this->dir . '/buf'), 'new binary');
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
            '--plugin' => '3.*',
        ];
    }

    private function tester(DLoadGetSpy $get, ProtobufRuntime|\Closure|null $runtime): CommandTester
    {
        $detect = $runtime instanceof \Closure ? $runtime : static fn(): ?ProtobufRuntime => $runtime;

        return new CommandTester(new GetGrpcToolsCommand(downloader: new DLoadDownloader($get), runtime: $detect));
    }

    /**
     * The display with the block messages unwrapped.
     */
    private function display(CommandTester $tester): string
    {
        return (string) \preg_replace('/\s*\n\s*(?:!\s+)?/', ' ', $tester->getDisplay());
    }
}
