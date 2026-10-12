<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Otel extends AbstractSection
{
    private const NAME = 'otel';

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
                'resource' => [
                    'service_name' => 'RoadRunner',
                    'service_version' => '1.0.0',
                ],
                'insecure' => true,
                'exporter' => 'otlp',
                'client' => 'http',
                'endpoint' => '127.0.0.1:4318',
            ],
        ];
    }
}
