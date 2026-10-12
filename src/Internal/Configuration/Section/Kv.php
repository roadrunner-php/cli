<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Kv extends AbstractSection
{
    private const NAME = 'kv';

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
                'local' => [
                    'driver' => 'memory',
                    'config' => new \stdClass(),
                ],
            ],
        ];
    }
}
