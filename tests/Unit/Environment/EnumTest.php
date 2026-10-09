<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Environment;

use Spiral\RoadRunner\Console\Environment\Enum;
use Spiral\RoadRunner\Console\Environment\Stability;
use Testo\Assert;
use Testo\Test;

#[Test]
final class EnumTest
{
    public function collectsConstantsByPrefix(): void
    {
        Assert::same(Enum::values(Stability::class, 'STABILITY_R'), ['STABILITY_RC' => 'RC']);
    }

    public function returnsEmptyListWhenNothingMatches(): void
    {
        Assert::same(Enum::values(Stability::class, 'ARCH_'), []);
    }

    public function returnsEmptyListForUnknownClass(): void
    {
        Assert::same(Enum::values('Spiral\RoadRunner\Console\NonExistent', 'X'), []);
    }
}
