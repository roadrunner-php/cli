<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Environment;

use Spiral\RoadRunner\Console\Environment\Environment;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class EnvironmentTest
{
    private const KEY = 'RR_CLI_TEST_ENVIRONMENT_KEY';

    #[AfterTest]
    public function cleanUp(): void
    {
        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
    }

    public function explicitVariablesWin(): void
    {
        $_ENV[self::KEY] = 'env';
        $_SERVER[self::KEY] = 'server';

        Assert::same(Environment::get(self::KEY, null, [self::KEY => 'explicit']), 'explicit');
    }

    public function envWinsOverServer(): void
    {
        $_ENV[self::KEY] = 'env';
        $_SERVER[self::KEY] = 'server';

        Assert::same(Environment::get(self::KEY), 'env');
    }

    public function fallsBackToServer(): void
    {
        $_SERVER[self::KEY] = 'server';

        Assert::same(Environment::get(self::KEY), 'server');
    }

    public function returnsDefaultWhenMissing(): void
    {
        Assert::same(Environment::get(self::KEY, 'default'), 'default');
        Assert::null(Environment::get(self::KEY));
    }

    public function ignoresNonStringValues(): void
    {
        Assert::same(Environment::get(self::KEY, 'default', [self::KEY => ['array']]), 'default');
    }
}
