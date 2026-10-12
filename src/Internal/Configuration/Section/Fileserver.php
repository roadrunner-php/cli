<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Fileserver extends AbstractSection
{
    private const NAME = 'fileserver';

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
                'address' => '127.0.0.1:10101',
                'calculate_etag' => true,
                'serve' => [
                    [
                        'prefix' => '/',
                        'root' => 'public',
                    ],
                ],
            ],
        ];
    }
}
