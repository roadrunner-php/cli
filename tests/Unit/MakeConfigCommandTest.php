<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\MakeConfigCommand;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class MakeConfigCommandTest
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

    public function isNamedMakeConfig(): void
    {
        Assert::same((new MakeConfigCommand())->getName(), 'make-config');
        Assert::same((new MakeConfigCommand('custom'))->getName(), 'custom');
    }

    public function writesDefaultConfiguration(): void
    {
        $status = $this->run(['--location' => $this->dir]);

        Assert::same($status, Command::SUCCESS);
        Assert::same(
            \array_keys($this->config()),
            ['version', 'rpc', 'server', 'http', 'jobs', 'kv', 'metrics'],
        );
    }

    public function writesSelectedPlugins(): void
    {
        $status = $this->run(['--location' => $this->dir, '--plugin' => ['kv']]);

        Assert::same($status, Command::SUCCESS);
        Assert::same(\array_keys($this->config()), ['version', 'rpc', 'kv']);
    }

    public function presetTakesPrecedenceOverPlugins(): void
    {
        $status = $this->run(['--location' => $this->dir, '--plugin' => ['kv'], '--preset' => 'web']);

        Assert::same($status, Command::SUCCESS);
        Assert::array(\array_keys($this->config()))->sameElementsAs(['version', 'rpc', 'http', 'jobs', 'server']);
    }

    public function keepsExistingConfiguration(): void
    {
        \file_put_contents($this->dir . '/.rr.yaml', 'existing');

        $status = $this->run(['--location' => $this->dir]);

        Assert::same($status, Command::FAILURE);
        Assert::same(\file_get_contents($this->dir . '/.rr.yaml'), 'existing');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function run(array $input): int
    {
        return (new CommandTester(new MakeConfigCommand()))->execute($input, ['interactive' => false]);
    }

    private function config(): array
    {
        return Yaml::parseFile($this->dir . '/.rr.yaml');
    }
}
