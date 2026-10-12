<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Grpc extends AbstractSection
{
    private const NAME = 'grpc';

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
                'listen' => 'tcp://127.0.0.1:9001',
                'proto' => [
                    'proto/helloworld.proto',
                ],
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
