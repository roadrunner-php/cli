<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\GitHub;

use Spiral\RoadRunner\Console\Internal\GitHub\Token;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class TokenTest
{
    private const VARIABLES = [
        'GITHUB_TOKEN',
        'DLOAD_TOKEN_GITHUB_COM',
        'DLOAD_TOKEN_API_GITHUB_COM',
        'DLOAD_TOKEN_GHE_MY__CORP_COM___8443',
        'DLOAD_TOKEN_GHE_EXAMPLE_COM',
        'DLOAD_TOKEN_127_0_0_1___8080',
        'DLOAD_TOKEN_LOCALHOST___8080',
        'DLOAD_TOKEN__0_0_0_0_0_0_0_1___8080',
    ];

    #[AfterTest]
    public function cleanUp(): void
    {
        foreach (self::VARIABLES as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }

    #[DataSet(['https://api.github.com'])]
    #[DataSet(['https://API.github.com:443/'], 'normalized')]
    public function publicGitHubUsesGitHubToken(string $url): void
    {
        $_SERVER['GITHUB_TOKEN'] = 'github';

        Assert::same(Token::forApiUrl($url), 'github');
    }

    public function publicGitHubPrefersItsServerVariable(): void
    {
        $_SERVER['GITHUB_TOKEN'] = 'github';
        $_SERVER['DLOAD_TOKEN_GITHUB_COM'] = 'server';

        Assert::same(Token::forApiUrl('https://api.github.com'), 'server');
    }

    public function customHostWithPortUsesItsVariable(): void
    {
        $_SERVER['GITHUB_TOKEN'] = 'github';
        $_SERVER['DLOAD_TOKEN_GHE_MY__CORP_COM___8443'] = 'ghe';

        Assert::same(Token::forApiUrl('https://GHE.my-corp.com:8443/api/v3'), 'ghe');
    }

    public function customHostIgnoresGitHubToken(): void
    {
        $_SERVER['GITHUB_TOKEN'] = 'github';

        Assert::null(Token::forApiUrl('https://ghe.example.com/api/v3'));
        Assert::null(Token::forApiUrl('http://api.github.com'));
    }

    #[DataSet(['http://127.0.0.1:8080/api/v3', 'DLOAD_TOKEN_127_0_0_1___8080'])]
    #[DataSet(['http://localhost:8080/api/v3', 'DLOAD_TOKEN_LOCALHOST___8080'])]
    #[DataSet(['http://[::1]:8080/api/v3', 'DLOAD_TOKEN__0_0_0_0_0_0_0_1___8080'])]
    public function plainHttpLoopbackReceivesToken(string $url, string $variable): void
    {
        $_SERVER[$variable] = 'local';

        Assert::same(Token::forApiUrl($url), 'local');
    }

    public function plainHttpRemoteHostReceivesNoToken(): void
    {
        $_SERVER['DLOAD_TOKEN_GHE_EXAMPLE_COM'] = 'ghe';

        Assert::null(Token::forApiUrl('http://ghe.example.com/api/v3'));
        Assert::same(Token::forApiUrl('https://ghe.example.com/api/v3'), 'ghe');
    }

    #[DataSet(['ftp://ghe.example.com'], 'unsupported scheme')]
    #[DataSet(['ghe.example.com'], 'no scheme')]
    #[DataSet(['https://-ghe.example.com'], 'malformed host')]
    #[DataSet(['https://[1.2.3.4]'], 'malformed IPv6')]
    public function unusableUrlReceivesNoToken(string $url): void
    {
        $_SERVER['DLOAD_TOKEN_GHE_EXAMPLE_COM'] = 'ghe';

        Assert::null(Token::forApiUrl($url));
    }

    public function emptyVariableIsNoToken(): void
    {
        $_SERVER['DLOAD_TOKEN_GITHUB_COM'] = '';
        $_SERVER['GITHUB_TOKEN'] = 'github';

        Assert::same(Token::forApiUrl('https://api.github.com'), 'github');
    }
}
