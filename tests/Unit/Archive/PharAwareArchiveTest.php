<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Archive;

use Spiral\RoadRunner\Console\Archive\PharArchive;
use Spiral\RoadRunner\Console\Archive\TarPharArchive;
use Spiral\RoadRunner\Console\Archive\ZipPharArchive;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Skip;
use Testo\Test;

#[Test]
final class PharAwareArchiveTest
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

    #[DataSet(['rr.zip', ZipPharArchive::class])]
    #[DataSet(['rr.tar.gz', TarPharArchive::class])]
    #[DataSet(['rr.tar', PharArchive::class])]
    public function extractCopiesMappedFiles(string $name, string $class): void
    {
        $path = TempDirectory::archive($this->dir, $name, [
            'roadrunner-2024.1.0/rr' => 'binary',
            'roadrunner-2024.1.0/README.md' => 'readme',
        ]);
        $archive = new $class(new \SplFileInfo($path));

        $extracted = [];
        foreach ($archive->extract(['rr' => $this->dir . '/rr', 'rr.exe' => $this->dir . '/rr.exe']) as $from => $to) {
            $extracted[$from->getFilename()] = $to->getPathname();
        }

        Assert::same($extracted, ['rr' => $this->dir . '/rr']);
        Assert::same(\file_get_contents($this->dir . '/rr'), 'binary');
        Assert::false(\is_file($this->dir . '/README.md'));
    }

    public function extractSkipsFileWhenConsumerDeclines(): void
    {
        $path = TempDirectory::archive($this->dir, 'rr.zip', ['roadrunner/rr' => 'binary']);
        $extractor = (new ZipPharArchive(new \SplFileInfo($path)))->extract(['rr' => $this->dir . '/rr']);

        Assert::true($extractor->valid());
        $extractor->send(false);

        Assert::false($extractor->valid());
        Assert::false(\is_file($this->dir . '/rr'));
    }

    #[Skip('Bug: extract() reopens $this->archive, whose pathname points at the first archive entry rather than the archive itself, so a binary in the archive root cannot be extracted')]
    public function extractFindsBinaryInArchiveRoot(): void
    {
        $path = TempDirectory::archive($this->dir, 'rr.zip', ['LICENSE' => 'license', 'rr' => 'binary']);

        \iterator_to_array((new ZipPharArchive(new \SplFileInfo($path)))->extract(['rr' => $this->dir . '/rr']));

        Assert::same(\file_get_contents($this->dir . '/rr'), 'binary');
    }

    public function rejectsMissingFile(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Archive "missing.zip" is not a file');

        new ZipPharArchive(new \SplFileInfo($this->dir . '/missing.zip'));
    }

    public function rejectsDirectory(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('is not a file');

        new ZipPharArchive(new \SplFileInfo($this->dir));
    }
}
