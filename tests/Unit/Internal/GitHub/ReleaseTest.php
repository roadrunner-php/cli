<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\GitHub;

use Spiral\RoadRunner\Console\Internal\GitHub\Release;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class ReleaseTest
{
    #[DataSet(['v2024.1.0', '2024.1.0', 'stable'])]
    #[DataSet(['2.12.3', '2.12.3', 'stable'])]
    #[DataSet(['v3.5.0-rc.1', '3.5.0-RC1', 'RC'])]
    #[DataSet(['v2.0.0-beta.2', '2.0.0-beta2', 'beta'])]
    #[DataSet(['v2.0.0-alpha1', '2.0.0-alpha1', 'alpha'])]
    #[DataSet(['v1.2.3.4', '1.2.3.4', 'stable'], 'four numbers')]
    public function parsesNameAndStabilityOfTag(string $tag, string $name, string $stability): void
    {
        $release = Release::fromApiResponse(['tag_name' => $tag, 'name' => 'Release']);

        Assert::same($release->name, $name);
        Assert::same($release->stability, $stability);
    }

    public function fallsBackToNameForInvalidTag(): void
    {
        $release = Release::fromApiResponse(['tag_name' => 'latest-build', 'name' => 'v2.1.0']);

        Assert::same($release->name, '2.1.0');
    }

    #[DataSet([['tag_name' => 'nightly', 'name' => 'Nightly']], 'name is not a version')]
    #[DataSet([['tag_name' => 'nightly', 'name' => null]], 'no name')]
    public function namesUnparsableReleaseAsDevBranch(array $response): void
    {
        $release = Release::fromApiResponse($response);

        Assert::same($release->name, 'dev-nightly');
        Assert::same($release->stability, 'dev');
    }

    public function keepsAssetNames(): void
    {
        $release = Release::fromApiResponse([
            'tag_name' => 'v3.4.0',
            'assets' => [
                ['name' => 'roadrunner-3.4.0-linux-amd64.tar.gz', 'browser_download_url' => 'https://example.com/a'],
                ['browser_download_url' => 'https://example.com/b'],
                'garbage',
                ['name' => 'roadrunner-3.4.0-windows-amd64.zip'],
            ],
        ]);

        Assert::same($release->assets, ['roadrunner-3.4.0-linux-amd64.tar.gz', 'roadrunner-3.4.0-windows-amd64.zip']);
    }

    #[DataSet([[]], 'missing')]
    #[DataSet([['tag_name' => '']], 'empty')]
    #[DataSet([['tag_name' => 3]], 'not a string')]
    public function requiresTag(array $response): never
    {
        Expect::exception(\UnexpectedValueException::class)->withMessageContaining('"tag_name"');

        Release::fromApiResponse($response);
    }

    #[DataSet(['v2024.1.0', '2024.*', true])]
    #[DataSet(['v2024.1.0', '^2023.3', false])]
    #[DataSet(['v2024.1.0', '>=2.0 <2025', true])]
    #[DataSet(['v3.1.0', '^2.0 || ^3.0', true], 'alternatives')]
    #[DataSet(['v2024.1.0-rc.1', '2024.*', true], 'wildcard matches pre-releases')]
    public function satisfiesComparesName(string $tag, string $constraint, bool $expected): void
    {
        Assert::same(Release::fromApiResponse(['tag_name' => $tag])->satisfies($constraint), $expected);
    }

    #[DataSet([
        ['v2023.3.12', 'v2024.2.0', 'v1.9.0', 'v2024.10.1', 'v2.12.3'],
        ['2024.10.1', '2024.2.0', '2023.3.12', '2.12.3', '1.9.0'],
    ], 'stable')]
    #[DataSet([
        ['v2024.1.0-alpha.1', 'v2024.1.0', 'v2024.1.0-beta.1', 'v2024.1.0-rc.1'],
        ['2024.1.0', '2024.1.0-RC1', '2024.1.0-beta1', '2024.1.0-alpha1'],
    ], 'pre-releases')]
    #[DataSet([
        ['v2025.1.15', 'v2.12.3', 'v3.0.0', 'v2023.3.0', 'v3.0.0-rc.1'],
        ['3.0.0', '3.0.0-RC1', '2025.1.15', '2023.3.0', '2.12.3'],
    ], 'calendar')]
    public function newestFirstOrdersVersionsDescending(array $tags, array $expected): void
    {
        $releases = \array_map(static fn(string $tag): Release => Release::fromApiResponse(['tag_name' => $tag]), $tags);

        \usort($releases, Release::newestFirst(...));

        Assert::same(\array_map(static fn(Release $release): string => $release->name, $releases), $expected);
    }
}
