<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Metrics extends AbstractSection
{
    private const NAME = 'metrics';

    public function render(): array
    {
        return [
            self::NAME => [
                'address' => '127.0.0.1:2112',
                'collect' => [
                    'app_metric' => [
                        'type' => 'histogram',
                        'help' => 'Custom application metric',
                        'labels' => [
                            'type'
                        ],
                        'buckets' => [
                            0.1,
                            0.2,
                            0.3,
                            1.0
                        ],
                        'objectives' => [
                            [
                                '1.4' => 2.3
                            ],
                            [
                                '2.0' => 1.4
                            ]
                        ]
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
