<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Archive;

use Spiral\RoadRunner\Console\Archive\ArchiveInterface;
use Spiral\RoadRunner\Console\Archive\Factory;
use Spiral\RoadRunner\Console\Archive\TarPharArchive;
use Spiral\RoadRunner\Console\Archive\ZipPharArchive;
use Spiral\RoadRunner\Console\Repository\GitHub\GitHubAsset;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class FactoryTest
{
    private string $dir;

    #[BeforeTest]
    public function createDirectory(): void
    {
        $this->dir = TempDirectory::create();
    }

    #[AfterTest]
    public function removeDirectory(): void
    {
        TempDirectory::remove($this->dir);
    }

    #[DataSet(['rr.zip', 'rr.zip', ZipPharArchive::class])]
    #[DataSet(['rr.zip', 'RR.ZIP', ZipPharArchive::class], 'upper-case extension')]
    #[DataSet(['rr.tar.gz', 'rr.tar.gz', TarPharArchive::class])]
    public function createPicksArchiveByExtension(string $source, string $name, string $class): void
    {
        $path = TempDirectory::archive($this->dir, $source, ['rr' => 'binary']);
        \rename($path, $this->dir . '/' . $name);

        $archive = (new Factory())->create(new \SplFileInfo($this->dir . '/' . $name));

        Assert::instanceOf($archive, $class);
    }

    public function createRejectsUnknownExtension(): never
    {
        \file_put_contents($this->dir . '/rr.rar', 'data');

        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Can not open the archive "rr.rar"');

        (new Factory())->create(new \SplFileInfo($this->dir . '/rr.rar'));
    }

    public function createReportsErrorsOfMatchingFormats(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Archive "missing.zip" is not a file');

        (new Factory())->create(new \SplFileInfo($this->dir . '/missing.zip'));
    }

    public function extendedMatcherTakesPriority(): void
    {
        $path = TempDirectory::archive($this->dir, 'rr.zip', ['rr' => 'binary']);
        $custom = new class implements ArchiveInterface {
            public function extract(iterable $mappings): \Generator
            {
                yield from [];
            }
        };

        $archive = (new Factory())
            ->extend(static fn(\SplFileInfo $file): ArchiveInterface => $custom)
            ->create(new \SplFileInfo($path));

        Assert::same($archive, $custom);
    }

    public function extendedMatcherMayDecline(): void
    {
        $path = TempDirectory::archive($this->dir, 'rr.zip', ['rr' => 'binary']);

        $archive = (new Factory())
            ->extend(static fn(\SplFileInfo $file): ?ArchiveInterface => null)
            ->create(new \SplFileInfo($path));

        Assert::instanceOf($archive, ZipPharArchive::class);
    }

    public function fromAssetDownloadsIntoTempDirectory(): void
    {
        $source = TempDirectory::archive($this->dir, 'source.zip', ['rr' => 'binary']);
        $temp = $this->dir . '/temp';
        \mkdir($temp);
        $asset = new GitHubAsset(
            new MockHttpClient(new MockResponse((string) \file_get_contents($source))),
            'roadrunner-2024.1.0-linux-amd64.zip',
            'https://example.com/rr.zip',
        );

        $archive = (new Factory())->fromAsset($asset, null, $temp);

        Assert::instanceOf($archive, ZipPharArchive::class);
        Assert::same(\file_get_contents($temp . '/roadrunner-2024.1.0-linux-amd64.zip'), \file_get_contents($source));
    }

    public function fromAssetRejectsMissingTempDirectory(): never
    {
        Expect::exception(\LogicException::class)
            ->withMessageContaining('is not writeable');

        (new Factory())->fromAsset(
            new GitHubAsset(new MockHttpClient(), 'rr.zip', 'https://example.com/rr.zip'),
            null,
            $this->dir . '/missing',
        );
    }

    public function fromAssetRethrowsDownloadErrors(): never
    {
        $asset = new GitHubAsset(
            new MockHttpClient(new MockResponse('', ['error' => 'connection refused'])),
            'rr.zip',
            'https://example.com/rr.zip',
        );

        Expect::exception(\RuntimeException::class)
            ->withMessageContaining('connection refused');

        (new Factory())->fromAsset($asset, null, $this->dir);
    }
}
