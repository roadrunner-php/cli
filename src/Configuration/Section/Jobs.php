<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Jobs extends AbstractSection
{
    private const NAME = 'jobs';

    public function render(): array
    {
        return [
            self::NAME => [
                'num_pollers' => 32,
                'pipeline_size' => 100000,
                'pool' => [
                    'command' => '',
                    'num_workers' => 10,
                    'max_jobs' => 0,
                    'allocate_timeout' => '60s',
                    'destroy_timeout' => '60s'
                ],
                'pipelines' => [
                    'test-local' => [
                        'driver' => 'memory',
                        'config' => [
                            'priority' => 10,
                            'prefetch' => 10000
                        ]
                    ],
                    'test-local-1' => [
                        'driver' => 'boltdb',
                        'config' => [
                            'file' => 'path/to/rr.db',
                            'priority' => 10,
                            'prefetch' => 10000
                        ]
                    ],
                    'test-local-2' => [
                        'driver' => 'amqp',
                        'config' => [
                            'prefetch' => 10,
                            'priority' => 1,
                            'durable' => false,
                            'delete_queue_on_stop' => false,
                            'queue' => 'test-1-queue',
                            'exchange' => 'default',
                            'exchange_type' => 'direct',
                            'routing_key' => 'test',
                            'exclusive' => false,
                            'multiple_ack' => false,
                            'requeue_on_fail' => false
                        ]
                    ],
                    'test-local-3' => [
                        'driver' => 'beanstalk',
                        'config' => [
                            'priority' => 11,
                            'tube_priority' => 1,
                            'tube' => 'default-1',
                            'reserve_timeout' => '10s'
                        ]
                    ],
                    'test-local-4' => [
                        'driver' => 'sqs',
                        'config' => [
                            'priority' => 10,
                            'prefetch' => 10,
                            'visibility_timeout' => 0,
                            'wait_time_seconds' => 0,
                            'queue' => 'default',
                            'attributes' => [
                                'DelaySeconds' => 0,
                                'MaximumMessageSize' => 262144,
                                'MessageRetentionPeriod' => 345600,
                                'ReceiveMessageWaitTimeSeconds' => 0,
                                'VisibilityTimeout' => 30
                            ],
                            'tags' => [
                                'test' => 'tag'
                            ]
                        ]
                    ],
                    'test-local-5' => [
                        'driver' => 'nats',
                        'config' => [
                            'priority' => 2,
                            'prefetch' => 100,
                            'subject' => 'default',
                            'stream' => 'foo',
                            'deliver_new' => true,
                            'rate_limit' => 100,
                            'delete_stream_on_stop' => false,
                            'delete_after_ack' => false
                        ]
                    ]
                ],
                'consume' => [
                    'test-local',
                    'test-local-1',
                    'test-local-2',
                    'test-local-3',
                    'test-local-4',
                    'test-local-5'
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
