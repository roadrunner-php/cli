<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
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
                'addr' => 'nats://127.0.0.1:4222',
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
