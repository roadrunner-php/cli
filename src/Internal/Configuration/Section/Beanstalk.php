<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Beanstalk extends AbstractSection
{
    private const NAME = 'beanstalk';

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
                'addr' => 'tcp://127.0.0.1:11300',
                'timeout' => '10s',
            ],
        ];
    }
}
