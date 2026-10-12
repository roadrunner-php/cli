<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Sqs extends AbstractSection
{
    private const NAME = 'sqs';

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
                'key' => 'access-key',
                'secret' => 'api-secret',
                'region' => 'us-west-1',
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
