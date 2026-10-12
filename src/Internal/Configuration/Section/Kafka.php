<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Kafka extends AbstractSection
{
    private const NAME = 'kafka';

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
                'brokers' => [
                    '127.0.0.1:9092',
                ],
            ],
        ];
    }

    #[\Override]
    public function getRequired(): array
    {
        return [
            Jobs::class,
        ];
    }
}
