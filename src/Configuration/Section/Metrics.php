<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Metrics extends AbstractSection
{
    private const NAME = 'metrics';

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
                'address' => '127.0.0.1:2112',
            ],
        ];
    }
}
