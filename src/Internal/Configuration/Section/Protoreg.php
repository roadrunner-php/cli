<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Protoreg extends AbstractSection
{
    private const NAME = 'protoreg';

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
                'proto_path' => [
                    'proto',
                ],
                'files' => [
                    'helloworld.proto',
                ],
            ],
        ];
    }

    #[\Override]
    public function getRequired(): array
    {
        return [
            Grpc::class,
        ];
    }
}
