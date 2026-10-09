<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository\GitHub;

use Spiral\RoadRunner\Console\Repository\GitHub\GitHubRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
use Testo\Test;

#[Test]
final class GitHubRepositoryTest
{
    public function nameIsOwnerAndRepository(): void
    {
        Assert::same(GitHubRepository::create('roadrunner-server', 'roadrunner')->getName(), 'roadrunner-server/roadrunner');
    }

    public function getReleasesFollowsPagination(): void
    {
        $requests = [];
        $pages = [
            1 => [[['name' => 'v2024.2.0', 'tag_name' => 'v2024.2.0']], '<https://api.github.com/x?page=2>; rel="next"'],
            2 => [[['name' => 'v2024.1.0', 'tag_name' => 'v2024.1.0']], '<https://api.github.com/x?page=1>; rel="prev"'],
        ];
        $client = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requests, $pages): MockResponse {
                $requests[] = [$method, $url, $options['normalized_headers']['accept'][0] ?? null];
                \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
                [$body, $link] = $pages[(int) $query['page']];

                return new MockResponse(\json_encode($body), ['response_headers' => ['link' => $link]]);
            },
        );

        $releases = GitHubRepository::create('roadrunner-server', 'roadrunner', $client)->getReleases();

        Assert::same(Releases::versions($releases), ['v2024.2.0', 'v2024.1.0']);
        Assert::same($requests, [
            ['GET', 'https://api.github.com/repos/roadrunner-server/roadrunner/releases?page=1&per_page=100', 'accept: application/vnd.github.v3+json'],
            ['GET', 'https://api.github.com/repos/roadrunner-server/roadrunner/releases?page=2&per_page=100', 'accept: application/vnd.github.v3+json'],
        ]);
    }

    public function getReleasesUsesCustomApiUrl(): void
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('[]');
        });

        GitHubRepository::create('roadrunner-server', 'roadrunner', $client, 'http://127.0.0.1:8080/api/v3/')
            ->getReleases()
            ->empty();

        Assert::same($urls, ['http://127.0.0.1:8080/api/v3/repos/roadrunner-server/roadrunner/releases?page=1&per_page=100']);
    }

    public function getReleasesStopsWithoutLinkHeader(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('[]');
        });

        $releases = GitHubRepository::create('roadrunner-server', 'roadrunner', $client)->getReleases();

        Assert::true($releases->empty());
        Assert::same($calls, 1);
    }
}
