<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Stub;

use Spiral\RoadRunner\Console\Repository\GitHub\GitHubAsset;
use Spiral\RoadRunner\Console\Repository\GitHub\GitHubRelease;
use Spiral\RoadRunner\Console\Repository\ReleaseInterface;
use Spiral\RoadRunner\Console\Repository\ReleasesCollection;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds GitHub releases and assets on top of a client that never reaches the network.
 */
final class Releases
{
    /**
     * @param list<string> $assets Asset file names.
     */
    public static function release(
        string $tag,
        array $assets = [],
        ?HttpClientInterface $client = null,
        string $repository = 'roadrunner-server/roadrunner',
    ): GitHubRelease {
        $client ??= new MockHttpClient();

        return new GitHubRelease(
            $client,
            $tag,
            $tag,
            $repository,
            \array_map(static fn(string $name): GitHubAsset => self::asset($name, $client), $assets),
        );
    }

    public static function asset(string $name, ?HttpClientInterface $client = null): GitHubAsset
    {
        return new GitHubAsset($client ?? new MockHttpClient(), $name, 'https://example.com/download/' . $name);
    }

    public static function collection(string ...$tags): ReleasesCollection
    {
        return new ReleasesCollection(\array_map(static fn(string $tag): GitHubRelease => self::release($tag), $tags));
    }

    /**
     * @param iterable<ReleaseInterface> $releases
     * @return list<string>
     */
    public static function versions(iterable $releases): array
    {
        $result = [];
        foreach ($releases as $release) {
            $result[] = $release->getVersion();
        }

        return $result;
    }
}
