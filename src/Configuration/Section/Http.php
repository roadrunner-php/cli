<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Http extends AbstractSection
{
    private const NAME = 'http';

    public function render(): array
    {
        return [
            self::NAME => [
                'address' => '127.0.0.1:8080',
                'internal_error_code' => 505,
                'access_logs' => false,
                'max_request_size' => 256,
                'middleware' => [
                    'headers',
                    'gzip'
                ],
                'trusted_subnets' => [
                    '10.0.0.0/8',
                    '127.0.0.0/8',
                    '172.16.0.0/12',
                    '192.168.0.0/16',
                    '::1/128',
                    'fc00::/7',
                    'fe80::/10'
                ],
                'new_relic' => [
                    'app_name' => 'app',
                    'license_key' => 'key'
                ],
                'cache' => [
                    'driver' => 'memory',
                    'cache_methods' => [
                        'GET',
                        'HEAD',
                        'POST'
                    ],
                    'config' => []
                ],
                'uploads' => [
                    'dir' => '/tmp',
                    'forbid' => [
                        '.php',
                        '.exe',
                        '.bat',
                        '.sh'
                    ],
                    'allow' => [
                        '.html',
                        '.aaa'
                    ]
                ],
                'headers' => [
                    'cors' => [
                        'allowed_origin' => '*',
                        'allowed_headers' => '*',
                        'allowed_methods' => 'GET,POST,PUT,DELETE',
                        'allow_credentials' => true,
                        'exposed_headers' => 'Cache-Control,Content-Language,Content-Type,Expires,Last-Modified,Pragma',
                        'max_age' => 600
                    ],
                    'request' => [
                        'input' => 'custom-header',
                    ],
                    'response' => [
                        'X-Powered-By' => 'RoadRunner'
                    ]
                ],
                'static' => [
                    'dir' => '.',
                    'forbid' => [''],
                    'calculate_etag' => false,
                    'weak' => false,
                    'allow' => [
                        '.txt',
                        '.php'
                    ],
                    'request' => [
                        'input' => 'custom-header'
                    ],
                    'response' => [
                        'output' => 'output-header'
                    ]
                ],
                'pool' => [
                    'debug' => false,
                    'command' => 'php my-super-app.php',
                    'num_workers' => 0,
                    'max_jobs' => 64,
                    'allocate_timeout' => '60s',
                    'destroy_timeout' => '60s',
                    'supervisor' => [
                        'watch_tick' => '1s',
                        'ttl' => '0s',
                        'idle_ttl' => '10s',
                        'max_worker_memory' => 128,
                        'exec_ttl' => '60s'
                    ]
                ],
                'ssl' => [
                    'address' => '127.0.0.1:443',
                    'acme' => [
                        'certs_dir' => 'rr_le_certs',
                        'email' => 'you-email-here@email',
                        'alt_http_port' => 80,
                        'alt_tlsalpn_port' => 443,
                        'challenge_type' => 'http-01',
                        'use_production_endpoint' => true,
                        'domains' => [
                            'your-cool-domain.here',
                            'your-second-domain.here'
                        ]
                    ],
                    'redirect' => true,
                    'cert' => '/ssl/server.crt',
                    'key' => '/ssl/server.key',
                    'root_ca' => '/ssl/root.crt'
                ],
                'fcgi' => [
                    'address' => 'tcp://0.0.0.0:7921'
                ],
                'http2' => [
                    'h2c' => false,
                    'max_concurrent_streams' => 128
                ]
            ]
        ];
    }

    public function getRequired(): array
    {
        return [
            Server::class
        ];
    }

    public static function getShortName(): string
    {
        return self::NAME;
    }
}
