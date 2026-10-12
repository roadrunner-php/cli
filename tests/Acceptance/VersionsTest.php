<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Test;

/**
 * `rr versions` lists the releases of the GitHub API set by RR_GITHUB_API_URL.
 */
#[Test]
final class VersionsTest
{
    use FakeGitHubCase;

    private const ARGS = ['versions', '--os=linux', '--arch=amd64'];

    public function listsStableReleasesNewestFirst(): void
    {
        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(self::releases($result->stdout), ['3.4.0', '3.2.0', '3.1.0', '2025.1.5', '2.12.3']);
        Assert::string($result->stdout)
            ->matchesRegex('/^\s*3\.4\.0\s+stable\s+✓\s+\(7\)\s+✓\s*$/mu')
            ->matchesRegex('/^\s*2025\.1\.5\s+stable\s+.*\(reason: incompatible version\)/mu');
        Assert::same(\count(FakeGitHub::releaseRequests()), 1);
    }

    public function listsPreReleasesOfLowerStability(): void
    {
        $result = $this->rr()->run([...self::ARGS, '--stability=beta']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(
            self::releases($result->stdout),
            ['3.5.0-RC1', '3.5.0-beta1', '3.4.0', '3.2.0', '3.1.0', '2025.1.5', '2.12.3'],
        );
        Assert::string($result->stdout)
            ->matchesRegex('/^\s*3\.5\.0-RC1\s+RC\s+✓\s+\(4\)\s+✓\s*$/mu')
            ->matchesRegex('/^\s*3\.5\.0-beta1\s+beta\s+✓\s+\(4\)\s+✓\s*$/mu');
    }

    public function filtersByVersionConstraint(): void
    {
        $result = $this->rr()->run([...self::ARGS, '--filter=^3.1 <3.4']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(self::releases($result->stdout), ['3.2.0', '3.1.0']);
    }

    public function explainsMissingAssembly(): void
    {
        $result = $this->rr()->run(['versions', '--os=darwin', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->stdout)
            ->matchesRegex('/^\s*3\.4\.0\s+.*\(reason: no assembly for amd64\)/mu')
            ->matchesRegex('/^\s*2\.12\.3\s+.*\(reason: incompatible version\)/mu');

        $result = $this->rr()->run(['versions', '--os=freebsd', '--arch=amd64']);

        Assert::string($result->stdout)->matchesRegex('/^\s*3\.4\.0\s+.*\(reason: no assembly for freebsd\)/mu');
    }

    public function followsPagination(): void
    {
        $result = $this->rr(FakeGitHub::url('paged'))->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(self::releases($result->stdout), ['3.4.0', '3.2.0', '3.1.0', '2025.1.5', '2.12.3']);
        Assert::same(\array_column(\array_column(FakeGitHub::releaseRequests(), 'query'), 'page'), ['1', '2', '3', '4']);
    }

    public function keepsGitHubTokenFromEnterpriseServer(): void
    {
        $result = $this->rr()->withEnv('GITHUB_TOKEN', 'github-com-token')->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\array_column(FakeGitHub::releaseRequests(), 'authorized'), [false]);
    }

    public function sendsTokenDeclaredForEnterpriseServer(): void
    {
        $result = $this->rr()->withEnv(self::tokenVariable(FakeGitHub::url()), 'server-token')->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\array_column(FakeGitHub::releaseRequests(), 'authorized'), [true]);
    }

    public function dropsTokenOnRedirectToAnotherServer(): void
    {
        $url = FakeGitHub::url('redirect');

        $result = $this->rr($url)->withEnv(self::tokenVariable($url), 'server-token')->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(self::releases($result->stdout), ['3.4.0', '3.2.0', '3.1.0', '2025.1.5', '2.12.3']);
        Assert::same(
            \array_map(
                static fn(array $request): array => [$request['status'], $request['authorized']],
                FakeGitHub::releaseRequests(),
            ),
            [[302, true], [200, false]],
        );
    }

    public function failsWhenRateLimited(): void
    {
        $result = $this->rr(FakeGitHub::url('rate-limit'))->run(self::ARGS);

        Assert::notSame($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->matchesRegex('/403|rate limit/i');
    }

    public function failsWhenGitHubIsUnreachable(): void
    {
        $result = $this->rr(FakeGitHub::unreachableUrl())->run(self::ARGS);

        Assert::notSame($result->exitCode, 0, (string) $result);
    }

    private static function tokenVariable(string $apiUrl): string
    {
        return 'DLOAD_TOKEN_127_0_0_1___' . \parse_url($apiUrl, \PHP_URL_PORT);
    }

    /**
     * @return list<string> The first column of the table.
     */
    private static function releases(string $output): array
    {
        \preg_match_all('/^\s+(\d[\w.-]*)\s+(?:stable|RC|beta|alpha|dev)\s/m', $output, $matches);

        return $matches[1];
    }
}
