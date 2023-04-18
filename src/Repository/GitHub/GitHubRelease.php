<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository\GitHub;

use Composer\Semver\VersionParser;
use Spiral\RoadRunner\Console\Repository\AssetsCollection;
use Spiral\RoadRunner\Console\Repository\Release;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-import-type GitHubAssetApiResponse from GitHubAsset
 *
 * @psalm-type GitHubReleaseApiResponse = array {
 *      name: string,
 *      assets: array<array-key, GitHubAssetApiResponse>
 * }
 */
final class GitHubRelease extends Release
{
    /**
     * @param iterable|array $assets
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        string $name,
        string $version,
        string $repository,
        iterable $assets = []
    ) {
        parent::__construct($name, $version, $repository, $assets);
    }

    public function getConfig(): string
    {
        $config = \vsprintf('https://raw.githubusercontent.com/%s/%s/.rr.yaml', [
            $this->getRepositoryName(),
            $this->getVersion(),
        ]);

        $response = $this->client->request('GET', $config);

        return $response->getContent();
    }

    /**
     * @param GitHubReleaseApiResponse $release
     */
    public static function fromApiResponse(GitHubRepository $repository, HttpClientInterface $client, array $release): self
    {
        if (! isset($release['name'])) {
            throw new \InvalidArgumentException(
                'Passed array must contain "name" value of type string'
            );
        }

        $instantiator = static function () use ($client, $release): \Generator {
            foreach ($release['assets'] ?? [] as $item) {
                yield GitHubAsset::fromApiResponse($client, $item);
            }
        };

        $name = self::getTagName($release);
        $version = $release['tag_name'] ?? $release['name'];

        return new self($client, $name, $version, $repository->getName(), AssetsCollection::from($instantiator));
    }

    /**
     * Returns pretty-formatted tag (release) name.
     *
     * Note: The return value is "pretty", but that does not mean that the
     * tag physically exists.
     *
     * @param array { tag_name: string, name: string } $release
     */
    private static function getTagName(array $release): string
    {
        $parser = new VersionParser();

        try {
            return $parser->normalize($release['tag_name']);
        } catch (\Throwable) {
            try {
                return $parser->normalize($release['name']);
            } catch (\Throwable) {
                return 'dev-' . $release['tag_name'];
            }
        }
    }
}
