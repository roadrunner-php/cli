<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Temporal extends AbstractSection
{
    private const NAME = 'temporal';

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
                'address' => '127.0.0.1:7233',
            ],
        ];
    }

    #[\Override]
    public function getRequired(): array
    {
        return [
            Server::class,
        ];
    }
}
