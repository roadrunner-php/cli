<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository;

use Spiral\RoadRunner\Console\Repository\AssetInterface;
use Spiral\RoadRunner\Console\Repository\AssetsCollection;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Testo\Assert;
use Testo\Test;

#[Test]
final class AssetsCollectionTest
{
    private const ASSETS = [
        'roadrunner-2024.1.0-linux-amd64.tar.gz',
        'roadrunner-2024.1.0-linux-amd64.deb',
        'roadrunner-2024.1.0-linux-arm64.tar.gz',
        'roadrunner-2024.1.0-darwin-arm64.tar.gz',
        'roadrunner-2024.1.0-windows-amd64.zip',
        'roadrunner-2024.1.0-unknown-musl-amd64.zip',
        'protoc-gen-php-grpc-2024.1.0-linux-amd64.tar.gz',
    ];

    public function onlyRoadrunnerDropsOtherBinaries(): void
    {
        $names = self::names(self::assets()->onlyRoadrunner());

        Assert::count($names, 6);
        Assert::array($names)->notContains('protoc-gen-php-grpc-2024.1.0-linux-amd64.tar.gz');
    }

    public function exceptDebPackagesIsCaseInsensitive(): void
    {
        $assets = (new AssetsCollection([
            Releases::asset('roadrunner-linux-amd64.deb'),
            Releases::asset('roadrunner-linux-amd64.DEB'),
            Releases::asset('roadrunner-linux-amd64.tar.gz'),
        ]))->exceptDebPackages();

        Assert::same(self::names($assets), ['roadrunner-linux-amd64.tar.gz']);
    }

    public function filtersByOperatingSystemAndArchitecture(): void
    {
        $assets = self::assets()
            ->onlyRoadrunner()
            ->exceptDebPackages()
            ->whereOperatingSystem('linux')
            ->whereArchitecture('amd64');

        Assert::same(self::names($assets), ['roadrunner-2024.1.0-linux-amd64.tar.gz']);
    }

    public function filterArgumentsAreCaseInsensitive(): void
    {
        $assets = self::assets()->whereOperatingSystem('Darwin')->whereArchitecture('ARM64');

        Assert::same(self::names($assets), ['roadrunner-2024.1.0-darwin-arm64.tar.gz']);
    }

    public function filtersMuslBuilds(): void
    {
        Assert::same(
            self::names(self::assets()->whereOperatingSystem('unknown-musl')),
            ['roadrunner-2024.1.0-unknown-musl-amd64.zip'],
        );
    }

    private static function assets(): AssetsCollection
    {
        return new AssetsCollection(\array_map(Releases::asset(...), self::ASSETS));
    }

    /**
     * @return list<string>
     */
    private static function names(AssetsCollection $assets): array
    {
        return \array_map(static fn(AssetInterface $asset): string => $asset->getName(), $assets->toArray());
    }
}
