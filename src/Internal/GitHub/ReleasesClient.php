<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\GitHub;

use Spiral\RoadRunner\Console\Internal\Environment\Environment;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lists the releases of the RoadRunner repository through the GitHub REST API.
 *
 * @internal
 */
final class ReleasesClient
{
    private const DEFAULT_API_URL = 'https://api.github.com';
    private const ENV_GITHUB_TOKEN = 'GITHUB_TOKEN';

    /**
     * Not `GITHUB_API_URL`: GitHub Actions sets that one in every job.
     */
    private const ENV_GITHUB_API_URL = 'RR_GITHUB_API_URL';

    private const REPOSITORY = 'roadrunner-server/roadrunner';

    /**
     * The largest page size GitHub accepts.
     */
    private const PER_PAGE = 100;

    private readonly string $apiUrl;

    /**
     * @param string $apiUrl Base URL of the GitHub REST API: GitHub Enterprise, a mirror or a mock server.
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        string $apiUrl = self::DEFAULT_API_URL,
    ) {
        $this->apiUrl = \rtrim($apiUrl, '/');
    }

    /**
     * Takes the API URL from RR_GITHUB_API_URL and the token from GITHUB_TOKEN, the token is sent to any API URL.
     */
    public static function fromEnvironment(): self
    {
        $token = Environment::get(self::ENV_GITHUB_TOKEN);
        $apiUrl = Environment::get(self::ENV_GITHUB_API_URL);

        $client = HttpClient::create([
            'headers' => \array_filter([
                'authorization' => $token !== null && $token !== '' ? 'token ' . $token : null,
            ]),
        ]);

        return new self($client, $apiUrl !== null && $apiUrl !== '' ? $apiUrl : self::DEFAULT_API_URL);
    }

    /**
     * @return list<Release> In the order of the API: the newest first, by creation date.
     * @throws ExceptionInterface
     */
    public function releases(): array
    {
        $releases = [];
        $page = 0;

        do {
            $response = $this->client->request('GET', $this->apiUrl . '/repos/' . self::REPOSITORY . '/releases', [
                'headers' => ['accept' => 'application/vnd.github.v3+json'],
                'query' => ['page' => ++$page, 'per_page' => self::PER_PAGE],
            ]);

            /** @var mixed $release */
            foreach ($response->toArray() as $release) {
                if (\is_array($release)) {
                    $releases[] = Release::fromApiResponse($release);
                }
            }

            $link = $response->getHeaders()['link'][0] ?? '';
        } while (\str_contains($link, 'rel="next"'));

        return $releases;
    }
}
