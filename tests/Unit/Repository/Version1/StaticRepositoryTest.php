<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository\Version1;

use Spiral\RoadRunner\Console\Repository\Version1\StaticRepository;
use Symfony\Component\HttpClient\MockHttpClient;
use Testo\Assert;
use Testo\Test;

#[Test]
final class StaticRepositoryTest
{
    public function listsLegacyReleases(): void
    {
        $repository = new StaticRepository(new MockHttpClient());

        $releases = $repository->getReleases();

        Assert::same($repository->getName(), 'spiral/roadrunner');
        Assert::same($releases->first()?->getVersion(), 'v1.9.1');
        Assert::true($releases->satisfies('^2.0')->empty());
    }

    public function exposesAssetsOfLegacyReleases(): void
    {
        $asset = (new StaticRepository(new MockHttpClient()))
            ->getReleases()
            ->first()
            ?->getAssets()
            ->whereOperatingSystem('linux')
            ->whereArchitecture('amd64')
            ->first();

        Assert::same($asset?->getName(), 'roadrunner-1.9.1-linux-amd64.tar.gz');
        Assert::same(
            $asset?->getUri(),
            'https://github.com/spiral/roadrunner/releases/download/v1.9.1/roadrunner-1.9.1-linux-amd64.tar.gz',
        );
    }
}
