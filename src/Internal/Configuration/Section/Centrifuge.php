<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Centrifuge extends AbstractSection
{
    private const NAME = 'centrifuge';

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
                'proxy_address' => 'tcp://127.0.0.1:30000',
                'grpc_api_address' => '127.0.0.1:10000',
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
