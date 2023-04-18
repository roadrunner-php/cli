<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\StyleInterface;

interface OptionInterface
{
    public function getName(): string;

    public function get(InputInterface $input, StyleInterface $io): string;
}
