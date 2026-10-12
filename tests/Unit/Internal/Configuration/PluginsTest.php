<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Configuration;

use Spiral\RoadRunner\Console\Internal\Configuration\Plugins;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Http;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Jobs;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Kv;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Metrics;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Rpc;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\SectionInterface;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Server;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Version;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
final class PluginsTest
{
    private const DEFAULTS = [
        Version::class,
        Rpc::class,
        Server::class,
        Http::class,
        Jobs::class,
        Kv::class,
        Metrics::class,
    ];

    /**
     * @return iterable<string, array{class-string<SectionInterface>}>
     */
    public static function sections(): iterable
    {
        foreach (\glob(\dirname(__DIR__, 4) . '/src/Internal/Configuration/Section/*.php') as $file) {
            $class = 'Spiral\\RoadRunner\\Console\\Internal\\Configuration\\Section\\' . \basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isInstantiable()) {
                yield $reflection->getShortName() => [$class];
            }
        }
    }

    public function usesDefaultPluginsWhenNothingRequested(): void
    {
        Assert::same(Plugins::fromPlugins([])->getPlugins(), self::DEFAULTS);
    }

    public function selectsRequestedPluginsByShortName(): void
    {
        Assert::array(Plugins::fromPlugins(['kv', 'http'])->getPlugins())
            ->sameElementsAs([Http::class, Kv::class]);
    }

    public function ignoresUnknownPlugins(): void
    {
        Assert::same(Plugins::fromPlugins(['unknown'])->getPlugins(), []);
    }

    public function webPresetSelectsHttpAndJobs(): void
    {
        Assert::array(Plugins::fromPreset('web')->getPlugins())
            ->sameElementsAs([Http::class, Jobs::class]);
    }

    public function unknownPresetFallsBackToDefaults(): void
    {
        Assert::same(Plugins::fromPreset('unknown')->getPlugins(), self::DEFAULTS);
    }

    /**
     * @param class-string<SectionInterface> $class
     */
    #[DataProvider('sections')]
    public function everySectionIsAvailableByShortName(string $class): void
    {
        Assert::same(Plugins::fromPlugins([$class::getShortName()])->getPlugins(), [$class]);
    }

    /**
     * @param class-string<SectionInterface> $class
     */
    #[DataProvider('sections')]
    public function everySectionRendersUnderItsShortName(string $class): void
    {
        $content = (new $class())->render();

        Assert::same(\array_keys($content), [$class::getShortName()]);
    }
}
