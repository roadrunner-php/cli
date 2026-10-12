<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Environment;

use Spiral\RoadRunner\Console\Internal\Environment\Environment;
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

    public function returnsNullWhenMissing(): void
    {
        Assert::null(Environment::get(self::KEY));
    }

    public function ignoresNonStringValues(): void
    {
        $_SERVER[self::KEY] = ['array'];

        Assert::null(Environment::get(self::KEY));
    }
}
