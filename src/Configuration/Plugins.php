<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration;

use Spiral\RoadRunner\Console\Configuration\Section\Http;
use Spiral\RoadRunner\Console\Configuration\Section\Jobs;
use Spiral\RoadRunner\Console\Configuration\Section\Kv;
use Spiral\RoadRunner\Console\Configuration\Section\Metrics;
use Spiral\RoadRunner\Console\Configuration\Section\Rpc;
use Spiral\RoadRunner\Console\Configuration\Section\SectionInterface;
use Spiral\RoadRunner\Console\Configuration\Section\Server;
use Spiral\RoadRunner\Console\Configuration\Section\Version;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Finder\Finder;

final class Plugins
{
    /**
     * @psalm-var array<class-string>
     *
     * Default plugins in a class-string format.
     */
    private array $defaultPlugins = [
        Version::class,
        Rpc::class,
        Server::class,
        Http::class,
        Jobs::class,
        Kv::class,
        Metrics::class,
    ];

    /**
     * @psalm-var non-empty-array<class-string<SectionInterface>>
     *
     * All plugins.
     */
    private readonly array $available;

    /**
     * @param string[] $requestedPlugins Requested plugins in a shortname format.
     */
    private function __construct(
        private readonly array $requestedPlugins,
    ) {
        $this->available = $this->getAvailable();
    }

    public static function fromPlugins(array $plugins): self
    {
        return new self($plugins);
    }

    public static function fromPreset(string $preset): self
    {
        $plugins = [];
        switch ($preset) {
            case Presets::WEB_PRESET_NANE:
                $plugins = Presets::WEB_PLUGINS;
        }
        /**
         * TODO: fix this
         */
        return new self(\array_map(static fn(string $plugin) => $plugin::getShortName(), $plugins));
    }

    public function getPlugins(): array
    {
        if ($this->requestedPlugins === []) {
            return $this->defaultPlugins;
        }

        $plugins = [];
        foreach ($this->available as $plugin) {
            if (\in_array($plugin::getShortName(), $this->requestedPlugins, true)) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    private function getAvailable(): array
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/Section')->name('*.php');

        $locator = new ClassLocator($finder);

        /** @var SectionInterface[] $available */
        $available = [];
        foreach ($locator->getClasses() as $class) {
            if ($this->isPlugin($class)) {
                $available[] = $class->getName();
            }
        }

        return $available;
    }

    private function isPlugin(\ReflectionClass $class): bool
    {
        return $class->implementsInterface(SectionInterface::class) && !$class->isAbstract() && !$class->isInterface();
    }
}
