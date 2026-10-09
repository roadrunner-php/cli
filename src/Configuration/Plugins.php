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
     * @psalm-var list<class-string<SectionInterface>>
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
     * @var string[]
     *
     * Requested plugins in a shortname format.
     */
    private array $requestedPlugins;

    /**
     * @psalm-var list<class-string<SectionInterface>>
     *
     * All plugins.
     */
    private array $available;

    /**
     * @param string[] $plugins
     */
    private function __construct(array $plugins)
    {
        $this->available = $this->getAvailable();
        $this->requestedPlugins = $plugins;
    }

    /**
     * @param string[] $plugins Plugin short names.
     */
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

        return new self(\array_map(static function (string $plugin) {
            return $plugin::getShortName();
        }, $plugins));
    }

    /**
     * @return list<class-string<SectionInterface>>
     */
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

    /**
     * @return list<class-string<SectionInterface>>
     */
    private function getAvailable(): array
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/Section')->name('*.php');

        $locator = new ClassLocator($finder);

        $available = [];
        foreach ($locator->getClasses() as $class) {
            if ($this->isPlugin($class)) {
                /** @var class-string<SectionInterface> $name */
                $name = $class->getName();
                $available[] = $name;
            }
        }

        return $available;
    }

    private function isPlugin(\ReflectionClass $class): bool
    {
        return $class->implementsInterface(SectionInterface::class) && !$class->isAbstract() && !$class->isInterface();
    }
}
