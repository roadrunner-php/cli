<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository\GitHub;

use Spiral\RoadRunner\Console\Repository\ReleasesCollection;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @psalm-import-type GitHubReleaseApiResponse from GitHubRelease
 */
final class GitHubRepository implements RepositoryInterface
{
    public const DEFAULT_API_URL = 'https://api.github.com';

    /**
     * @var string
     */
    private const URL_RELEASES = '%s/repos/%s/releases';

    /**
     * The largest page size GitHub accepts.
     */
    private const PER_PAGE = 100;

    private HttpClientInterface $client;
    private string $name;
    private string $apiUrl;

    /**
     * @var array|string[]
     */
    private array $headers = [
        'accept' => 'application/vnd.github.v3+json',
    ];

    /**
     * @param string $apiUrl Base URL of the GitHub REST API: GitHub Enterprise, a mirror or a mock server.
     */
    public function __construct(
        string $owner,
        string $repository,
        ?HttpClientInterface $client = null,
        string $apiUrl = self::DEFAULT_API_URL,
    ) {
        $this->name = $owner . '/' . $repository;
        $this->client = $client ?? HttpClient::create();
        $this->apiUrl = \rtrim($apiUrl, '/');
    }

    public static function create(
        string $owner,
        string $name,
        ?HttpClientInterface $client = null,
        string $apiUrl = self::DEFAULT_API_URL,
    ): GitHubRepository {
        return new GitHubRepository($owner, $name, $client, $apiUrl);
    }

    /**
     *
     * @throws ExceptionInterface
     */
    #[\Override]
    public function getReleases(): ReleasesCollection
    {
        return ReleasesCollection::from(function () {
            $page = 0;

            do {
                $response = $this->releasesRequest(++$page);

                /** @psalm-var GitHubReleaseApiResponse $data */
                foreach ($response->toArray() as $data) {
                    yield GitHubRelease::fromApiResponse($this, $this->client, $data);
                }
            } while ($this->hasNextPage($response));
        });
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws TransportExceptionInterface
     * @see HttpClientInterface::request()
     */
    protected function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        // Merge headers with defaults
        $options['headers'] = \array_merge($this->headers, (array) ($options['headers'] ?? []));

        return $this->client->request($method, $uri, $options);
    }

    /**
     * @param positive-int $page
     * @throws TransportExceptionInterface
     */
    private function releasesRequest(int $page): ResponseInterface
    {
        return $this->request('GET', $this->uri(self::URL_RELEASES), [
            'query' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
            ],
        ]);
    }

    private function uri(string $pattern): string
    {
        return \sprintf($pattern, $this->apiUrl, $this->getName());
    }

    /**
     * @throws ExceptionInterface
     */
    private function hasNextPage(ResponseInterface $response): bool
    {
        $headers = $response->getHeaders();
        $link = $headers['link'] ?? [];

        if (! isset($link[0])) {
            return false;
        }

        return \str_contains($link[0], 'rel="next"');
    }
}
