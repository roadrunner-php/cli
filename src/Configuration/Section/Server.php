<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

final class Server extends AbstractSection
{
    private const NAME = 'server';

    public function render(): array
    {
        return [
            self::NAME => [
                'on_init' => [
                    'command' => 'any php or script here',
                    'exec_timeout' => '20s',
                ],
                'command' => 'php psr-worker.php',
                'user' => '',
                'group' => '',
                'relay' => 'pipes',
                'relay_timeout' => '60s'
            ]
        ];
    }

    public static function getShortName(): string
    {
        return self::NAME;
    }
}
