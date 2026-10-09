<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration\Section;

abstract class AbstractSection implements SectionInterface
{
    #[\Override]
    public function getRequired(): array
    {
        return [];
    }

    #[\Override]
    abstract public function render(): array;
}
