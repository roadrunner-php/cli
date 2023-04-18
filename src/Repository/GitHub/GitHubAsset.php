<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository\GitHub;

use Spiral\RoadRunner\Console\Repository\Asset;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-type GitHubAssetApiResponse = array {
 *      name: string,
 *      browser_download_url: string
 * }
 */
final class GitHubAsset extends Asset
{
    public function __construct(
        private readonly HttpClientInterface $client,
        string $name,
        string $uri,
    ) {
        parent::__construct($name, $uri);
    }

    /**
     * @param GitHubAssetApiResponse $asset
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    public static function fromApiResponse(HttpClientInterface $client, array $asset): self
    {
        // Validate name
        if (! isset($asset['name']) || ! \is_string($asset['name'])) {
            throw new \InvalidArgumentException(
                'Passed array must contain "name" value of type string'
            );
        }

        // Validate uri
        if (! isset($asset['browser_download_url']) || ! \is_string($asset['browser_download_url'])) {
            throw new \InvalidArgumentException(
                'Passed array must contain "browser_download_url" key of type string'
            );
        }

        return new self($client, $asset['name'], $asset['browser_download_url']);
    }

    /**
     * @throws ExceptionInterface
     */
    public function download(\Closure $progress = null): \Traversable
    {
        $response = $this->client->request('GET', $this->getUri(), [
            'on_progress' => $progress,
        ]);

        foreach ($this->client->stream($response) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
