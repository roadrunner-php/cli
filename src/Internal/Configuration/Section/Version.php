<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration\Section;

/**
 * @internal
 */
final class Version extends AbstractSection
{
    private const NAME = 'version';
    private const CONFIG_VERSION = '3';

    #[\Override]
    public static function getShortName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function render(): array
    {
        return [
            self::NAME => self::CONFIG_VERSION,
        ];
    }
}
