<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository;

use Spiral\RoadRunner\Console\Environment\Stability;
use Spiral\RoadRunner\Console\Repository\ReleasesCollection;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Skip;
use Testo\Test;

#[Test]
final class ReleasesCollectionTest
{
    public function satisfiesKeepsMatchingReleases(): void
    {
        $releases = Releases::collection('v2023.3.0', 'v2024.1.0', 'v2024.2.1')->satisfies('^2024.1');

        Assert::same(Releases::versions($releases), ['v2024.1.0', 'v2024.2.1']);
    }

    public function satisfiesAppliesEveryConstraint(): void
    {
        $releases = Releases::collection('v2023.3.0', 'v2024.1.0', 'v2024.2.1')->satisfies('>=2024.1', '<2024.2');

        Assert::same(Releases::versions($releases), ['v2024.1.0']);
    }

    public function pipeSeparatedConstraintsAllApply(): void
    {
        $releases = Releases::collection('v2023.3.0', 'v2024.1.0', 'v2024.2.1')->satisfies('>=2024.1 | <2024.2 |');

        Assert::same(Releases::versions($releases), ['v2024.1.0']);
    }

    public function notSatisfiesDropsMatchingReleases(): void
    {
        $releases = Releases::collection('v2023.3.0', 'v2024.1.0', 'v2024.2.1')->notSatisfies('^2024.1');

        Assert::same(Releases::versions($releases), ['v2023.3.0']);
    }

    public function withAssetsDropsEmptyReleases(): void
    {
        $releases = new ReleasesCollection([
            Releases::release('v2024.1.0', ['roadrunner-2024.1.0-linux-amd64.tar.gz']),
            Releases::release('v2024.1.1'),
        ]);

        Assert::same(Releases::versions($releases->withAssets()), ['v2024.1.0']);
    }

    public function sortByVersionOrdersStableReleasesDescending(): void
    {
        $releases = Releases::collection('v2023.3.12', 'v2024.2.0', 'v1.9.0', 'v2024.10.1', 'v2.12.3');

        Assert::same(
            Releases::versions($releases->sortByVersion()),
            ['v2024.10.1', 'v2024.2.0', 'v2023.3.12', 'v2.12.3', 'v1.9.0'],
        );
    }

    public function sortByVersionPutsReleaseCandidateBelowFinalRelease(): void
    {
        $releases = Releases::collection('v2024.1.0-rc.1', 'v2023.3.12', 'v2024.1.0');

        Assert::same(
            Releases::versions($releases->sortByVersion()),
            ['v2024.1.0', 'v2024.1.0-rc.1', 'v2023.3.12'],
        );
    }

    #[Skip('Bug: sortByVersion() replaces "-beta"/"-alpha"/"-RC" with a numeric weight, so v2024.1.0-beta.1 compares as 2024.1.0.2.1 and sorts above v2024.1.0')]
    public function sortByVersionPutsPreReleasesBelowFinalRelease(): void
    {
        $releases = Releases::collection('v2024.1.0-alpha.1', 'v2024.1.0', 'v2024.1.0-beta.1', 'v2024.1.0-rc.1');

        Assert::same(
            Releases::versions($releases->sortByVersion()),
            ['v2024.1.0', 'v2024.1.0-rc.1', 'v2024.1.0-beta.1', 'v2024.1.0-alpha.1'],
        );
    }

    public function stableKeepsOnlyStableReleases(): void
    {
        $releases = Releases::collection('v2024.1.0-rc.1', 'v2024.1.0', 'v2024.1.0-beta.1');

        Assert::same(Releases::versions($releases->stable()), ['v2024.1.0']);
    }

    public function stabilityKeepsExactStability(): void
    {
        $releases = Releases::collection('v2024.1.0-rc.1', 'v2024.1.0', 'v2024.1.0-beta.1');

        Assert::same(Releases::versions($releases->stability(Stability::STABILITY_BETA)), ['v2024.1.0-beta.1']);
    }

    #[DataSet([Stability::STABILITY_STABLE, ['v2024.1.0']])]
    #[DataSet([Stability::STABILITY_RC, ['v2024.1.0-rc.1', 'v2024.1.0']])]
    #[DataSet([Stability::STABILITY_BETA, ['v2024.1.0-rc.1', 'v2024.1.0', 'v2024.1.0-beta.1']])]
    #[DataSet([Stability::STABILITY_DEV, ['v2024.1.0-rc.1', 'v2024.1.0', 'v2024.1.0-beta.1', 'v2024.1.0-alpha.1']])]
    public function minimumStabilityKeepsEqualOrMoreStable(string $stability, array $expected): void
    {
        $releases = Releases::collection('v2024.1.0-rc.1', 'v2024.1.0', 'v2024.1.0-beta.1', 'v2024.1.0-alpha.1');

        Assert::same(Releases::versions($releases->minimumStability($stability)), $expected);
    }
}
