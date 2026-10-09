<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Amqp extends AbstractSection
{
    private const NAME = 'amqp';

    #[\Override]
    public static function getShortName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function render(): array
    {
        return [
            self::NAME => [
                // Jobs pipelines refer to this connection name via `config.connection`
                'default' => [
                    'addr' => 'amqp://guest:guest@127.0.0.1:5672/',
                ],
            ],
        ];
    }
}
