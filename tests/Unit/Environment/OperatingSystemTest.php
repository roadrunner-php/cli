<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Environment;

use Spiral\RoadRunner\Console\Environment\OperatingSystem;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class OperatingSystemTest
{
    public function allListsEveryOperatingSystem(): void
    {
        Assert::same(OperatingSystem::all(), [
            'OS_DARWIN' => 'darwin',
            'OS_BSD' => 'freebsd',
            'OS_LINUX' => 'linux',
            'OS_WINDOWS' => 'windows',
            'OS_ALPINE' => 'unknown-musl',
        ]);
    }

    #[DataSet(['linux', true])]
    #[DataSet(['unknown-musl', true])]
    #[DataSet(['Linux', false], 'case-sensitive')]
    #[DataSet(['macos', false])]
    public function isValid(string $value, bool $expected): void
    {
        Assert::same(OperatingSystem::isValid($value), $expected);
    }

    public function detectsCurrentOperatingSystem(): void
    {
        $expected = match (\PHP_OS_FAMILY) {
            'Windows' => OperatingSystem::OS_WINDOWS,
            'BSD' => OperatingSystem::OS_BSD,
            'Darwin' => OperatingSystem::OS_DARWIN,
            'Linux' => OperatingSystem::OS_LINUX,
            default => null,
        };

        if ($expected === null) {
            Expect::exception(\OutOfRangeException::class);
        }

        Assert::same(OperatingSystem::createFromGlobals(), $expected);
    }
}
