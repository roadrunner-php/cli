<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

interface SectionInterface
{
    public static function getShortName(): string;

    public function render(): array;

    /**
     * @return list<class-string<SectionInterface>> Sections this one depends on.
     */
    public function getRequired(): array;
}
