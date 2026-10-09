<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * The `.rr.yaml` that `rr get` writes next to the binary.
 */
#[Test]
final class ConfigurationTest
{
    use FakeGitHubCase;

    private const ARGS = ['get', '--os=linux', '--arch=amd64'];

    public function createsDefaultConfiguration(): void
    {
        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::array($this->dir->configSections())->contains('version')->contains('server')->contains('http')->contains('kv');
    }

    public function skipsConfigurationWithNoConfigOption(): void
    {
        $result = $this->rr()->run([...self::ARGS, '--no-config']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::true($this->dir->has('rr'));
        Assert::false($this->dir->has('.rr.yaml'));
    }

    #[DataSet([['-p', 'http', '-p', 'jobs']], 'plugins')]
    #[DataSet([['--preset=web']], 'preset')]
    public function generatesConfigurationForSelectedPlugins(array $options): void
    {
        $result = $this->rr()->run([...self::ARGS, ...$options]);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::array($this->dir->configSections())->contains('http')->contains('jobs')->notContains('kv');
    }

    public function keepsExistingConfiguration(): void
    {
        $this->dir->write('.rr.yaml', "version: '3'\n# mine\n");

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::true($this->dir->has('rr'));
        Assert::same($this->dir->read('.rr.yaml'), "version: '3'\n# mine\n");
    }

    #[DataSet(['yes', true])]
    #[DataSet(['no', false])]
    public function asksWhetherToCreateConfiguration(string $answer, bool $created): void
    {
        $result = $this->rr()->run(self::ARGS, [$answer]);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->matchesRegex('/\.rr\.yaml/');
        Assert::true($this->dir->has('rr'));
        Assert::same($this->dir->has('.rr.yaml'), $created);
    }
}
