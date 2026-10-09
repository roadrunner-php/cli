<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\Repository\ReleaseInterface;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\InMemoryRepository;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Spiral\RoadRunner\Console\VersionsCommand;
use Spiral\RoadRunner\Version;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Test;

#[Test]
final class VersionsCommandTest
{
    public function describesItself(): void
    {
        $command = new VersionsCommand();

        Assert::same($command->getName(), 'versions');
        Assert::same($command->getDescription(), 'Returns a list of all available RoadRunner versions');
    }

    public function listsReleasesWithBinariesNewestFirst(): void
    {
        $major = self::compatibleMajor();

        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64'],
            Releases::release("v$major.1.0", ["roadrunner-$major.1.0-linux-amd64.tar.gz"]),
            Releases::release("v$major.2.0", ["roadrunner-$major.2.0-linux-amd64.tar.gz", "roadrunner-$major.2.0-darwin-arm64.tar.gz"]),
            Releases::release("v$major.3.0"),
        );

        Assert::string($display)
            ->matchesRegex("/$major\\.2\\.0\\s+stable\\s+✓\\s+\\(2\\)\\s+✓\\s*\\n\\s*$major\\.1\\.0\\s+stable\\s+✓\\s+\\(1\\)\\s+✓/u");
        Assert::string($display)->notContains("$major.3.0");
    }

    public function explainsMissingAssembly(): void
    {
        $major = self::compatibleMajor();

        $display = self::run(
            ['--os' => 'darwin', '--arch' => 'amd64'],
            Releases::release("v$major.1.0", ["roadrunner-$major.1.0-linux-amd64.tar.gz"]),
            Releases::release("v$major.2.0", ["roadrunner-$major.2.0-darwin-arm64.tar.gz"]),
        );

        Assert::string($display)
            ->contains('(reason: no assembly for darwin)')
            ->contains('(reason: no assembly for amd64)');
    }

    public function marksIncompatibleVersions(): void
    {
        if (Version::constraint() === '*') {
            throw new SkipTest('Every version is compatible when RoadRunner is installed from a branch');
        }

        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64'],
            Releases::release('v1.9.0', ['roadrunner-1.9.0-linux-amd64.tar.gz']),
        );

        Assert::string($display)->contains('(reason: incompatible version)');
    }

    public function filtersByStabilityAndVersion(): void
    {
        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64', '--stability' => 'beta', '--filter' => '^2024.1'],
            Releases::release('v2024.1.0-beta.1', ['roadrunner-2024.1.0-beta.1-linux-amd64.tar.gz']),
            Releases::release('v2024.1.0-alpha.1', ['roadrunner-2024.1.0-alpha.1-linux-amd64.tar.gz']),
            Releases::release('v2023.3.12', ['roadrunner-2023.3.12-linux-amd64.tar.gz']),
        );

        Assert::string($display)
            ->contains('2024.1.0-beta1')
            ->notContains('alpha1')
            ->notContains('2023.3.12');
    }

    /**
     * Major version of a release that satisfies the installed RoadRunner constraint.
     */
    private static function compatibleMajor(): string
    {
        $constraint = Version::constraint();

        return $constraint === '*' ? '2024' : \substr($constraint, 0, -2);
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function run(array $input, ReleaseInterface ...$releases): string
    {
        $repository = new InMemoryRepository(...$releases);
        $command = new class($repository) extends VersionsCommand {
            public function __construct(
                private readonly RepositoryInterface $repository,
            ) {
                parent::__construct();
            }

            protected function getRepository(): RepositoryInterface
            {
                return $this->repository;
            }
        };

        $tester = new CommandTester($command);
        Assert::same($tester->execute($input, ['interactive' => false]), 0);

        return $tester->getDisplay();
    }
}
