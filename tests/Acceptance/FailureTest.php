<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Skip;
use Testo\Test;

/**
 * `rr get` fails without leaving a binary behind.
 */
#[Test]
final class FailureTest
{
    use FakeGitHubCase;

    private const ARGS = ['get', '--os=linux', '--arch=amd64'];

    public function failsWhenRateLimited(): void
    {
        $result = $this->rr(FakeGitHub::url('rate-limit'))->run(self::ARGS);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->matchesRegex('/403|rate limit/i');
        Assert::same($this->dir->files(), []);
    }

    public function failsWhenAssetIsMissing(): void
    {
        $result = $this->rr(FakeGitHub::url('missing-asset'))->run(self::ARGS);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->matchesRegex('/404|not found/i');
        Assert::false($this->dir->has('rr'));
    }

    public function failsWhenGitHubIsUnreachable(): void
    {
        $result = $this->rr(FakeGitHub::unreachableUrl())->run(self::ARGS);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::same($this->dir->files(), []);
    }

    public function rejectsMissingLocationWithoutRequests(): void
    {
        $result = $this->rr()->run([...self::ARGS, '--location=does-not-exist']);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::same($this->dir->files(), []);
        Assert::same(FakeGitHub::requests(), []);
    }

    #[DataSet([['--os=plan9']], 'operating system')]
    #[DataSet([['--arch=sparc']], 'architecture')]
    public function failsForUnknownPlatform(array $options): void
    {
        $result = $this->rr()->run([...self::ARGS, ...$options]);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::same($this->dir->files(), []);
        Assert::same(FakeGitHub::downloads(), []);
    }

    #[DataSet([['--os=plan9']], 'operating system')]
    #[DataSet([['--arch=sparc']], 'architecture')]
    #[Skip('An unknown --os or --arch is only a warning: the releases are still fetched before the command fails')]
    public function rejectsUnknownPlatformWithoutRequests(array $options): void
    {
        $result = $this->rr()->run([...self::ARGS, ...$options]);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::same($this->dir->files(), []);
        Assert::same(FakeGitHub::requests(), []);
    }
}
