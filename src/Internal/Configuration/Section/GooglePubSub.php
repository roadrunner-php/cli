<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class GooglePubSub extends AbstractSection
{
    private const NAME = 'google_pub_sub';

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
                'insecure' => true,
                'endpoint' => '127.0.0.1:8085',
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
