<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\Environment\RoadRunnerVersion;
use Spiral\RoadRunner\Console\Internal\GitHub\ReleasesClient;
use Spiral\RoadRunner\Console\VersionsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Testo\Assert;
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
            ["v$major.1.0" => ["roadrunner-$major.1.0-linux-amd64.tar.gz"]],
            ["v$major.2.0" => ["roadrunner-$major.2.0-linux-amd64.tar.gz", "roadrunner-$major.2.0-darwin-arm64.tar.gz"]],
            ["v$major.3.0" => []],
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
            ["v$major.1.0" => ["roadrunner-$major.1.0-linux-amd64.tar.gz"]],
            ["v$major.2.0" => ["roadrunner-$major.2.0-darwin-arm64.tar.gz"]],
        );

        Assert::string($display)
            ->contains('(reason: no assembly for darwin)')
            ->contains('(reason: no assembly for amd64)');
    }

    public function marksIncompatibleVersions(): void
    {
        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64'],
            ['v1.9.0' => ['roadrunner-1.9.0-linux-amd64.tar.gz']],
        );

        Assert::string($display)->contains('(reason: incompatible version)');
    }

    public function filtersByStabilityAndVersion(): void
    {
        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64', '--stability' => 'beta', '--filter' => '^2024.1'],
            ['v2024.1.0-beta.1' => ['roadrunner-2024.1.0-beta.1-linux-amd64.tar.gz']],
            ['v2024.1.0-alpha.1' => ['roadrunner-2024.1.0-alpha.1-linux-amd64.tar.gz']],
            ['v2023.3.12' => ['roadrunner-2023.3.12-linux-amd64.tar.gz']],
        );

        Assert::string($display)
            ->contains('2024.1.0-beta1')
            ->notContains('alpha1')
            ->notContains('2023.3.12');
    }

    public function acceptsAlternativeConstraints(): void
    {
        $display = self::run(
            ['--os' => 'linux', '--arch' => 'amd64', '--filter' => '^2023.3 || ~2024.1.0'],
            ['v2024.1.0' => ['roadrunner-2024.1.0-linux-amd64.tar.gz']],
            ['v2023.3.12' => ['roadrunner-2023.3.12-linux-amd64.tar.gz']],
            ['v2024.2.0' => ['roadrunner-2024.2.0-linux-amd64.tar.gz']],
        );

        Assert::string($display)
            ->contains('2024.1.0')
            ->contains('2023.3.12')
            ->notContains('2024.2.0');
    }

    /**
     * Major version of a release that satisfies the installed RoadRunner constraint.
     */
    private static function compatibleMajor(): string
    {
        return \substr(RoadRunnerVersion::constraint(), 0, -2);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> ...$releases Asset names by release tag.
     */
    private static function run(array $input, array ...$releases): string
    {
        $body = [];
        foreach ($releases as $release) {
            foreach ($release as $tag => $assets) {
                $body[] = [
                    'tag_name' => $tag,
                    'name' => $tag,
                    'assets' => \array_map(static fn(string $name): array => ['name' => $name], $assets),
                ];
            }
        }

        $client = new ReleasesClient(new MockHttpClient(new MockResponse(\json_encode($body))));
        $tester = new CommandTester(new VersionsCommand(releases: $client));
        Assert::same($tester->execute($input, ['interactive' => false]), 0);

        return $tester->getDisplay();
    }
}
