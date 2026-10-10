<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Test;

/**
 * Which token `rr get` sends to a GitHub Enterprise Server set by RR_GITHUB_API_URL.
 */
#[Test]
final class AuthenticationTest
{
    use FakeGitHubCase;

    private const ARGS = ['get', '--os=linux', '--arch=amd64'];

    public function keepsGitHubTokenFromEnterpriseServer(): void
    {
        $result = $this->rr()->withEnv('GITHUB_TOKEN', 'github-com-token')->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::false(\in_array(true, \array_column(FakeGitHub::releaseRequests(), 'authorized'), true));
    }

    public function sendsTokenDeclaredForEnterpriseServer(): void
    {
        $port = (string) \parse_url(FakeGitHub::url(), \PHP_URL_PORT);

        $result = $this->rr()->withEnv('DLOAD_TOKEN_127_0_0_1_' . $port, 'server-token')->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\array_column(FakeGitHub::releaseRequests(), 'authorized'), [true]);
    }
}
