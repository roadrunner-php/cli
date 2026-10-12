<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\GitHub;

use Spiral\RoadRunner\Console\Internal\GitHub\Release;
use Spiral\RoadRunner\Console\Internal\GitHub\ReleasesClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class ReleasesClientTest
{
    #[AfterTest]
    public function cleanUp(): void
    {
        unset($_SERVER['RR_GITHUB_API_URL'], $_SERVER['GITHUB_TOKEN']);
    }

    public function followsPagination(): void
    {
        $requests = [];
        $pages = [
            1 => [[['tag_name' => 'v3.2.0']], '<https://api.github.com/x?page=2>; rel="next"'],
            2 => [[['tag_name' => 'v3.1.0'], 'garbage'], '<https://api.github.com/x?page=1>; rel="prev"'],
        ];
        $client = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requests, $pages): MockResponse {
                $requests[] = [$method, $url, $options['normalized_headers']['accept'][0] ?? null];
                \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
                [$body, $link] = $pages[(int) $query['page']];

                return new MockResponse(\json_encode($body), ['response_headers' => ['link' => $link]]);
            },
        );

        $releases = (new ReleasesClient($client))->releases();

        Assert::same(self::names($releases), ['3.2.0', '3.1.0']);
        Assert::same($requests, [
            ['GET', 'https://api.github.com/repos/roadrunner-server/roadrunner/releases?page=1&per_page=100', 'accept: application/vnd.github.v3+json'],
            ['GET', 'https://api.github.com/repos/roadrunner-server/roadrunner/releases?page=2&per_page=100', 'accept: application/vnd.github.v3+json'],
        ]);
    }

    public function stopsWithoutLinkHeader(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('[]');
        });

        Assert::same((new ReleasesClient($client))->releases(), []);
        Assert::same($calls, 1);
    }

    public function usesCustomApiUrl(): void
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('[]');
        });

        (new ReleasesClient($client, 'http://127.0.0.1:8080/api/v3/'))->releases();

        Assert::same($urls, ['http://127.0.0.1:8080/api/v3/repos/roadrunner-server/roadrunner/releases?page=1&per_page=100']);
    }

    public function readsApiUrlFromEnvironment(): void
    {
        // The unsupported scheme makes the client fail before any connection, with the URL in the message
        $_SERVER['RR_GITHUB_API_URL'] = 'ftp://github.mock/api';

        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('ftp://github.mock/api/repos/roadrunner-server/roadrunner/releases');

        ReleasesClient::fromEnvironment()->releases();
    }

    /**
     * @param list<Release> $releases
     * @return list<string>
     */
    private static function names(array $releases): array
    {
        return \array_map(static fn(Release $release): string => $release->name, $releases);
    }
}
