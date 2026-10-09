<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository\GitHub;

use Spiral\RoadRunner\Console\Repository\GitHub\GitHubAsset;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class GitHubAssetTest
{
    public function fromApiResponseReadsNameAndDownloadUrl(): void
    {
        $asset = GitHubAsset::fromApiResponse(new MockHttpClient(), [
            'name' => 'roadrunner-2024.1.0-linux-amd64.tar.gz',
            'browser_download_url' => 'https://example.com/rr.tar.gz',
        ]);

        Assert::same($asset->getName(), 'roadrunner-2024.1.0-linux-amd64.tar.gz');
        Assert::same($asset->getUri(), 'https://example.com/rr.tar.gz');
    }

    #[DataSet([['browser_download_url' => 'https://example.com'], '"name"'], 'missing name')]
    #[DataSet([['name' => 1, 'browser_download_url' => 'https://example.com'], '"name"'], 'non-string name')]
    #[DataSet([['name' => 'rr.zip'], '"browser_download_url"'], 'missing url')]
    #[DataSet([['name' => 'rr.zip', 'browser_download_url' => null], '"browser_download_url"'], 'null url')]
    public function fromApiResponseValidatesFields(array $data, string $field): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining($field);

        GitHubAsset::fromApiResponse(new MockHttpClient(), $data);
    }

    public function downloadStreamsBodyFromUri(): void
    {
        $requested = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method . ' ' . $url;

            return new MockResponse(['first-', 'second']);
        });
        $asset = new GitHubAsset($client, 'rr.zip', 'https://example.com/rr.zip');

        $content = \implode('', \iterator_to_array($asset->download(), false));

        Assert::same($content, 'first-second');
        Assert::same($requested, 'GET https://example.com/rr.zip');
    }

    public function downloadReportsProgress(): void
    {
        $client = new MockHttpClient(new MockResponse('0123456789', ['response_headers' => ['content-length' => '10']]));
        $asset = new GitHubAsset($client, 'rr.zip', 'https://example.com/rr.zip');
        $downloaded = 0;

        \iterator_to_array($asset->download(static function (int $size) use (&$downloaded): void {
            $downloaded = $size;
        }), false);

        Assert::same($downloaded, 10);
    }
}
