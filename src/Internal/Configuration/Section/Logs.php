<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Logs extends AbstractSection
{
    private const NAME = 'logs';

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
                'mode' => 'development',
                'level' => 'debug',
            ],
        ];
    }
}
