<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Rpc extends AbstractSection
{
    private const NAME = 'rpc';

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
                'listen' => 'tcp://127.0.0.1:6001',
            ],
        ];
    }
}
