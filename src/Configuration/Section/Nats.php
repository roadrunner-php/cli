<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Nats extends AbstractSection
{
    private const NAME = 'nats';

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
                'addr' => 'demo.nats.io',
            ],
        ];
    }
}
