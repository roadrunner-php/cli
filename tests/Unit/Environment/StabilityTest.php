<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Environment;

use Spiral\RoadRunner\Console\Environment\Stability;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
final class StabilityTest
{
    public function allListsEveryStability(): void
    {
        Assert::same(Stability::all(), [
            'STABILITY_STABLE' => 'stable',
            'STABILITY_RC' => 'RC',
            'STABILITY_BETA' => 'beta',
            'STABILITY_ALPHA' => 'alpha',
            'STABILITY_DEV' => 'dev',
        ]);
    }

    public function weightsFollowStabilityOrder(): void
    {
        Assert::true(Stability::toInt(Stability::STABILITY_STABLE) > Stability::toInt(Stability::STABILITY_RC));
        Assert::true(Stability::toInt(Stability::STABILITY_RC) > Stability::toInt(Stability::STABILITY_BETA));
        Assert::true(Stability::toInt(Stability::STABILITY_BETA) > Stability::toInt(Stability::STABILITY_ALPHA));
        Assert::true(Stability::toInt(Stability::STABILITY_ALPHA) > Stability::toInt(Stability::STABILITY_DEV));
    }

    public function unknownStabilityWeighsAsDev(): void
    {
        Assert::same(Stability::toInt('unknown'), Stability::toInt(Stability::STABILITY_DEV));
    }

    #[DataSet(['stable', true])]
    #[DataSet(['RC', true])]
    #[DataSet(['dev', true])]
    #[DataSet(['rc', false], 'case-sensitive')]
    #[DataSet(['', false], 'empty')]
    public function isValid(string $value, bool $expected): void
    {
        Assert::same(Stability::isValid($value), $expected);
    }
}
