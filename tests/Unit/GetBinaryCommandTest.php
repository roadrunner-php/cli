<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\Downloader\DLoadDownloader;
use Spiral\RoadRunner\Console\GetBinaryCommand;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\DLoadGetSpy;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class GetBinaryCommandTest
{
    private string $dir;
    private string $target;
    private string $cwd;

    /**
     * The command also looks for ".rr.yaml" in the working directory, so each test gets its own.
     */
    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
        $this->target = $this->dir . '/bin';
        \mkdir($this->target);
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

    public function describesItself(): void
    {
        $command = new GetBinaryCommand();

        Assert::same($command->getName(), 'get-binary');
        Assert::same($command->getDescription(), 'Install or update RoadRunner binary');
    }

    public function keepsOptions(): void
    {
        Assert::array(\array_keys((new GetBinaryCommand())->getDefinition()->getOptions()))
            ->sameElementsAs(['os', 'arch', 'filter', 'location', 'stability', 'plugin', 'preset', 'no-config']);
    }

    public function installsBinaryThroughDLoad(): void
    {
        $get = new DLoadGetSpy();
        $tester = $this->tester($get);

        $status = $tester->execute(
            $this->input(['--no-config' => true, '--stability' => 'beta']),
            ['interactive' => false],
        );

        Assert::same($status, 0);
        Assert::count($get->calls, 1);
        Assert::same($get->calls[0]->getArgument('software'), ['rr:^2024.1@beta']);
        Assert::same($get->calls[0]->getOption('path'), $this->target);
        Assert::same($get->calls[0]->getOption('os'), 'linux');
        Assert::same($get->calls[0]->getOption('arch'), 'amd64');
        Assert::same(\file_get_contents($this->target . '/rr'), 'new binary');
        Assert::false(\is_file($this->target . '/.rr.yaml'));
        Assert::string($tester->getDisplay())
            ->contains('Version:          ^2024.1')
            ->contains('Stability:        beta')
            ->contains('dload: rr:^2024.1@beta')
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('Your project is now ready in ' . $this->target)
            ->contains('$ rr serve');
    }

    public function installsWindowsBinary(): void
    {
        $tester = $this->tester(new DLoadGetSpy(binary: 'rr.exe'));

        $status = $tester->execute(
            $this->input(['--no-config' => true, '--os' => 'windows']),
            ['interactive' => false],
        );

        Assert::same($status, 0);
        Assert::string($tester->getDisplay())->contains('$ rr.exe serve');
    }

    public function failsWhenDLoadFails(): void
    {
        $tester = $this->tester(new DLoadGetSpy(exitCode: 1));

        $status = $tester->execute($this->input(['--preset' => 'web']), ['interactive' => false]);

        Assert::same($status, 1);
        Assert::false(\is_file($this->target . '/.rr.yaml'));
        Assert::string($tester->getDisplay())->notContains('Your project is now ready');
    }

    public function generatesConfigurationForPreset(): void
    {
        $tester = $this->tester(new DLoadGetSpy());

        $status = $tester->execute($this->input(['--preset' => 'web']), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::array(\array_keys(Yaml::parseFile($this->target . '/.rr.yaml')))
            ->sameElementsAs(['version', 'rpc', 'http', 'jobs', 'server']);
    }

    public function generatesConfigurationForPlugins(): void
    {
        $tester = $this->tester(new DLoadGetSpy());

        $status = $tester->execute($this->input(['--plugin' => ['kv']]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::array(\array_keys(Yaml::parseFile($this->target . '/.rr.yaml')))
            ->sameElementsAs(['version', 'rpc', 'kv']);
    }

    public function keepsExistingConfiguration(): void
    {
        \file_put_contents($this->target . '/.rr.yaml', 'existing');
        $tester = $this->tester(new DLoadGetSpy());

        $status = $tester->execute($this->input(['--plugin' => ['kv']]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::same(\file_get_contents($this->target . '/.rr.yaml'), 'existing');
    }

    public function keepsConfigurationInWorkingDirectory(): void
    {
        \file_put_contents($this->dir . '/cwd/.rr.yaml', 'existing');
        $tester = $this->tester(new DLoadGetSpy());

        $status = $tester->execute($this->input(['--plugin' => ['kv']]), ['interactive' => false]);

        Assert::same($status, 0);
        Assert::false(\is_file($this->target . '/.rr.yaml'));
    }

    public function keepsExistingBinaryUnlessConfirmed(): void
    {
        \file_put_contents($this->target . '/rr', 'old binary');
        $get = new DLoadGetSpy();
        $tester = $this->tester($get);

        $status = $tester->execute($this->input(['--preset' => 'web']), ['interactive' => false]);

        Assert::same($status, 1);
        Assert::same($get->calls, []);
        Assert::same(\file_get_contents($this->target . '/rr'), 'old binary');
        Assert::true(\is_file($this->target . '/.rr.yaml'));
        Assert::string($tester->getDisplay())
            ->contains('RoadRunner binary file already exists!')
            ->contains('Skipping RoadRunner installation...')
            ->contains('RoadRunner has not been installed');
    }

    public function overwritesExistingBinaryWhenConfirmed(): void
    {
        \file_put_contents($this->target . '/rr', 'old binary');
        $get = new DLoadGetSpy();
        $tester = $this->tester($get);
        $tester->setInputs(['yes']);

        $status = $tester->execute($this->input(['--no-config' => true]));

        Assert::same($status, 0);
        Assert::true($get->calls[0]->getOption('force'));
        Assert::same(\file_get_contents($this->target . '/rr'), 'new binary');
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function input(array $input): array
    {
        return $input + [
            '--location' => $this->target,
            '--os' => 'linux',
            '--arch' => 'amd64',
            '--filter' => '^2024.1',
        ];
    }

    private function tester(DLoadGetSpy $get): CommandTester
    {
        return new CommandTester(new GetBinaryCommand(downloader: new DLoadDownloader($get)));
    }
}
