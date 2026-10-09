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
    private string $cwd;

    /**
     * The command also looks for ".rr.yaml" in the working directory, so each test gets its own.
     */
    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
        $this->cwd = (string) \getcwd();
        \mkdir($this->dir . '/cwd');
        \chdir($this->dir . '/cwd');
    }

    #[AfterTest]
    public function removeDirectory(): void
    {
        \chdir($this->cwd);
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

    public function keepsConfigurationOfWorkingDirectory(): void
    {
        \file_put_contents($this->dir . '/cwd/.rr.yaml', 'existing');

        $status = $this->run(['--location' => $this->dir]);

        Assert::same($status, Command::FAILURE);
        Assert::false(\is_file($this->dir . '/.rr.yaml'));
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
