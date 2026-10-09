<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Skip;
use Testo\Test;

/**
 * `rr get` into a directory that already has a RoadRunner binary.
 */
#[Test]
final class ExistingBinaryTest
{
    use FakeGitHubCase;

    private const ARGS = ['get', '--os=linux', '--arch=amd64', '--no-config'];
    private const OLD = "old rr\n";

    public function keepsBinaryWithoutInteraction(): void
    {
        $this->dir->write('rr', self::OLD);

        $result = $this->rr()->run(self::ARGS);

        Assert::string($result->output())->matchesRegex('/already exists/i');
        Assert::same($this->dir->read('rr'), self::OLD);
    }

    public function keepsBinaryWhenOverwriteIsDeclined(): void
    {
        $this->dir->write('rr', self::OLD);

        $result = $this->rr()->run(self::ARGS, ['no']);

        Assert::string($result->output())->matchesRegex('/already exists/i');
        Assert::same($this->dir->read('rr'), self::OLD);
    }

    #[DataSet([null], 'no interaction')]
    #[DataSet([['no']], 'declined')]
    #[Skip('A skipped installation exits with 0 and reports "Your project is now ready"')]
    public function failsWhenBinaryIsKept(?array $answers): void
    {
        $this->dir->write('rr', self::OLD);

        $result = $this->rr()->run(self::ARGS, $answers);

        Assert::same($result->exitCode, 1, (string) $result);
    }

    public function overwritesBinaryWhenConfirmed(): void
    {
        $this->dir->write('rr', self::OLD);

        $result = $this->rr()->run(self::ARGS, ['yes']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('rr')), 'fake rr 3.4.0 linux amd64');
    }
}
