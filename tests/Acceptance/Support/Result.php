<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

final class Result
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {}

    public function output(): string
    {
        return $this->stdout . $this->stderr;
    }

    public function __toString(): string
    {
        return \sprintf("exit code %d\n--- stdout ---\n%s\n--- stderr ---\n%s", $this->exitCode, $this->stdout, $this->stderr);
    }
}
