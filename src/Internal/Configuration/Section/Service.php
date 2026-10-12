<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Service extends AbstractSection
{
    private const NAME = 'service';

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
                'some_service' => [
                    'command' => 'php service.php',
                    'process_num' => 1,
                    'remain_after_exit' => true,
                    'restart_sec' => 1,
                ],
            ],
        ];
    }
}
