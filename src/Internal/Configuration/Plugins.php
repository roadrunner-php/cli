<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration;

use Spiral\RoadRunner\Console\Internal\Configuration\Section\Amqp;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Beanstalk;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Boltdb;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Broadcast;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Endure;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Fileserver;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Grpc;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Http;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Jobs;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Kv;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Logs;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Metrics;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Nats;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Otel;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Redis;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Reload;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Rpc;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\SectionInterface;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Server;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Service;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Sqs;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Status;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Tcp;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Temporal;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Version;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Websockets;

/**
 * @internal
 */
final class Plugins
{
    /**
     * Every section a plugin can be requested by.
     *
     * @var list<class-string<SectionInterface>>
     */
    private const AVAILABLE = [
        Amqp::class,
        Beanstalk::class,
        Boltdb::class,
        Broadcast::class,
        Endure::class,
        Fileserver::class,
        Grpc::class,
        Http::class,
        Jobs::class,
        Kv::class,
        Logs::class,
        Metrics::class,
        Nats::class,
        Otel::class,
        Redis::class,
        Reload::class,
        Rpc::class,
        Server::class,
        Service::class,
        Sqs::class,
        Status::class,
        Tcp::class,
        Temporal::class,
        Version::class,
        Websockets::class,
    ];

    /**
     * @var list<class-string<SectionInterface>>
     */
    private const DEFAULT = [
        Version::class,
        Rpc::class,
        Server::class,
        Http::class,
        Jobs::class,
        Kv::class,
        Metrics::class,
    ];

    /**
     * @param string[] $requestedPlugins Plugin short names.
     */
    private function __construct(
        private readonly array $requestedPlugins,
    ) {}

    /**
     * @param string[] $plugins Plugin short names.
     */
    public static function fromPlugins(array $plugins): self
    {
        return new self($plugins);
    }

    public static function fromPreset(string $preset): self
    {
        $plugins = $preset === Presets::WEB_PRESET_NANE ? Presets::WEB_PLUGINS : [];

        return new self(\array_map(static fn(string $plugin): string => $plugin::getShortName(), $plugins));
    }

    /**
     * @return list<class-string<SectionInterface>>
     */
    public function getPlugins(): array
    {
        if ($this->requestedPlugins === []) {
            return self::DEFAULT;
        }

        $plugins = [];
        foreach (self::AVAILABLE as $plugin) {
            if (\in_array($plugin::getShortName(), $this->requestedPlugins, true)) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
