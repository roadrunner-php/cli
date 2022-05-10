<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Kv extends AbstractSection
{
    private const NAME = 'kv';

    public function render(): array
    {
        return [
            self::NAME => [
                'boltdb-south' => [
                    'driver' => 'boltdb',
                    'config' => [
                        'file' => 'rr.db',
                        'permissions' => 0777,
                        'interval' => 40
                    ]
                ],
                'us-central-kv' => [
                    'driver' => 'memcached',
                    'config' => [
                        'addr' => [
                            'localhost:11211'
                        ]
                    ]
                ],
                'fast-kv-fr' => [
                    'driver' => 'redis',
                    'config' => [
                        'addrs' => [
                            'localhost:6379'
                        ],
                        'master_name' => '',
                        'username' => '',
                        'password' => '',
                        'db' => 0,
                        'sentinel_password' => '',
                        'route_by_latency' => false,
                        'route_randomly' => false,
                        'dial_timeout' => 0,
                        'max_retries' => 1,
                        'min_retry_backoff' => 0,
                        'max_retry_backoff' => 0,
                        'pool_size' => 0,
                        'min_idle_conns' => 0,
                        'max_conn_age' => 0,
                        'read_timeout' => 0,
                        'write_timeout' => 0,
                        'pool_timeout' => 0,
                        'idle_timeout' => 0,
                        'idle_check_freq' => 0,
                        'read_only' => false
                    ]
                ],
                'local-memory' => [
                    'driver' => 'memory',
                    'config' => [
                        'interval' => 1
                    ]
                ]
            ]
        ];
    }

    public static function getShortName(): string
    {
        return self::NAME;
    }
}
