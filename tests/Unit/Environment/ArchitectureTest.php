<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Environment;

use Spiral\RoadRunner\Console\Environment\Architecture;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class ArchitectureTest
{
    public function allListsEveryArchitecture(): void
    {
        Assert::same(Architecture::all(), [
            'ARCH_X86_64' => 'amd64',
            'ARCH_ARM_64' => 'arm64',
        ]);
    }

    #[DataSet(['amd64', true])]
    #[DataSet(['arm64', true])]
    #[DataSet(['x86_64', false], 'uname alias is not a release architecture')]
    #[DataSet(['AMD64', false], 'case-sensitive')]
    public function isValid(string $value, bool $expected): void
    {
        Assert::same(Architecture::isValid($value), $expected);
    }

    public function detectsCurrentArchitecture(): void
    {
        $uname = \php_uname('m');
        $expected = match (true) {
            \in_array($uname, ['AMD64', 'amd64', 'x86', 'x64', 'x86_64'], true) => Architecture::ARCH_X86_64,
            \in_array($uname, ['arm64', 'aarch64'], true) => Architecture::ARCH_ARM_64,
            default => null,
        };

        if ($expected === null) {
            Expect::exception(\OutOfRangeException::class);
        }

        Assert::same(Architecture::createFromGlobals(), $expected);
    }
}
