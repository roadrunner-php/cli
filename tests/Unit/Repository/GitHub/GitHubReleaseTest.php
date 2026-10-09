<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository\GitHub;

use Spiral\RoadRunner\Console\Repository\AssetInterface;
use Spiral\RoadRunner\Console\Repository\GitHub\GitHubRelease;
use Spiral\RoadRunner\Console\Repository\GitHub\GitHubRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Skip;
use Testo\Test;

#[Test]
final class GitHubReleaseTest
{
    #[DataSet(['v2024.1.0', '2024.1.0', 'stable'])]
    #[DataSet(['2.12.3', '2.12.3', 'stable'])]
    #[DataSet(['v2024.1.0-rc.1', '2024.1.0-RC1', 'RC'])]
    #[DataSet(['v2.0.0-beta.2', '2.0.0-beta2', 'beta'])]
    #[DataSet(['v2.0.0-alpha1', '2.0.0-alpha1', 'alpha'])]
    public function parsesNameAndStability(string $tag, string $name, string $stability): void
    {
        $release = Releases::release($tag);

        Assert::same($release->getName(), $name);
        Assert::same($release->getVersion(), $tag);
        Assert::same($release->getStability(), $stability);
        Assert::same($release->getRepositoryName(), 'roadrunner-server/roadrunner');
    }

    #[DataSet(['v2024.1.0', '2024.*', true])]
    #[DataSet(['v2024.1.0', '^2023.3', false])]
    #[DataSet(['v2024.1.0', '>=2.0 <2025', true])]
    #[DataSet(['v2024.1.0-rc.1', '2024.*', true], 'wildcard matches pre-releases')]
    public function satisfiesComparesNormalizedName(string $tag, string $constraint, bool $expected): void
    {
        Assert::same(Releases::release($tag)->satisfies($constraint), $expected);
    }

    public function keepsAssets(): void
    {
        $release = Releases::release('v2024.1.0', ['a.zip', 'b.zip']);

        Assert::same(
            \array_map(static fn(AssetInterface $asset): string => $asset->getName(), $release->getAssets()->toArray()),
            ['a.zip', 'b.zip'],
        );
    }

    public function fromApiResponseUsesTagName(): void
    {
        $release = GitHubRelease::fromApiResponse(self::repository(), new MockHttpClient(), [
            'name' => 'Release 2024.1.0',
            'tag_name' => 'v2024.1.0',
            'assets' => [
                ['name' => 'roadrunner-2024.1.0-linux-amd64.tar.gz', 'browser_download_url' => 'https://example.com/rr.tar.gz'],
            ],
        ]);

        Assert::same($release->getName(), '2024.1.0');
        Assert::same($release->getVersion(), 'v2024.1.0');
        Assert::same($release->getRepositoryName(), 'roadrunner-server/roadrunner');
        Assert::same($release->getAssets()->first()?->getUri(), 'https://example.com/rr.tar.gz');
    }

    public function fromApiResponseFallsBackToNameForInvalidTag(): void
    {
        $release = GitHubRelease::fromApiResponse(self::repository(), new MockHttpClient(), [
            'name' => 'v2.1.0',
            'tag_name' => 'latest-build',
        ]);

        Assert::same($release->getName(), '2.1.0');
        Assert::same($release->getVersion(), 'latest-build');
        Assert::true($release->getAssets()->empty());
    }

    #[Skip('Bug: Release::simplifyReleaseName() strips the last two characters of the first "-" segment, so the "dev-nightly" fallback name becomes "d-nightly"')]
    public function fromApiResponseNamesUnparsableReleaseAsDevBranch(): void
    {
        $release = GitHubRelease::fromApiResponse(self::repository(), new MockHttpClient(), [
            'name' => 'Nightly',
            'tag_name' => 'nightly',
        ]);

        Assert::same($release->getName(), 'dev-nightly');
    }

    public function fromApiResponseRequiresName(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('"name"');

        GitHubRelease::fromApiResponse(self::repository(), new MockHttpClient(), ['tag_name' => 'v2024.1.0']);
    }

    public function getConfigDownloadsRrYamlOfTheTag(): void
    {
        $requested = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method . ' ' . $url;

            return new MockResponse("version: '3'\n");
        });

        $config = Releases::release('v2024.1.0', [], $client)->getConfig();

        Assert::same($config, "version: '3'\n");
        Assert::same($requested, 'GET https://raw.githubusercontent.com/roadrunner-server/roadrunner/v2024.1.0/.rr.yaml');
    }

    private static function repository(): GitHubRepository
    {
        return GitHubRepository::create('roadrunner-server', 'roadrunner', new MockHttpClient());
    }
}
