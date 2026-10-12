<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Internal\Environment\RoadRunnerVersion;
use Spiral\RoadRunner\Console\Internal\Environment\Stability;
use Spiral\RoadRunner\Console\Internal\GitHub\Release;
use Spiral\RoadRunner\Console\Internal\GitHub\ReleasesClient;
use Spiral\RoadRunner\Console\Internal\Option\ArchitectureOption;
use Spiral\RoadRunner\Console\Internal\Option\OperatingSystemOption;
use Spiral\RoadRunner\Console\Internal\Option\StabilityOption;
use Spiral\RoadRunner\Console\Internal\Option\VersionFilterOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @psalm-api
 */
final class VersionsCommand extends Command
{
    private OperatingSystemOption $os;
    private ArchitectureOption $arch;
    private VersionFilterOption $version;
    private StabilityOption $stability;

    /**
     * @param ReleasesClient|null $releases Configured from the environment on every run by default.
     */
    public function __construct(
        ?string $name = null,
        private readonly ?ReleasesClient $releases = null,
    ) {
        parent::__construct($name ?? 'versions');

        $this->os = new OperatingSystemOption($this);
        $this->arch = new ArchitectureOption($this);
        $this->stability = new StabilityOption($this);

        $this->version = new VersionFilterOption($this, default: '*');
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Returns a list of all available RoadRunner versions';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filter = $this->version->get($input, $io);
        $constraint = \trim($filter);
        $stability = $this->stability->get($input, $io);
        $os = $this->os->get($input, $io);
        $arch = $this->arch->get($input, $io);

        $output->writeln('');
        $output->writeln(' Environment:');
        $output->writeln(\sprintf('   - Version:          <info>%s</info>', $filter));
        $output->writeln(\sprintf('   - Stability:        <info>%s</info>', $stability));
        $output->writeln('');

        $minimumStability = Stability::toInt($stability);
        $releases = \array_filter(
            ($this->releases ?? ReleasesClient::fromEnvironment())->releases(),
            static fn(Release $release): bool => $release->assets !== []
                && Stability::toInt($release->stability) >= $minimumStability
                && ($constraint === '' || $release->satisfies($constraint)),
        );
        \usort($releases, Release::newestFirst(...));

        $rows = [];
        foreach ($releases as $release) {
            $rows[] = [
                $release->name,
                self::stabilityToString($release->stability),
                \sprintf('<fg=green> ✓ </> (<comment>%d</comment>)', \count($release->assets)),
                self::compatibilityToString($release, $os, $arch),
            ];
        }

        $io->table(['Release', 'Stability', 'Binaries', 'Compatibility'], $rows);

        return self::SUCCESS;
    }

    private static function compatibilityToString(Release $release, string $os, string $arch): string
    {
        $template = '<fg=red> ✖ </> (reason: <comment>%s</comment>)';

        if (! $release->satisfies(RoadRunnerVersion::constraint())) {
            return \sprintf($template, 'incompatible version');
        }

        $assets = \array_filter(
            $release->assets,
            static fn(string $asset): bool => \str_contains($asset, '-' . \strtolower($os) . '-'),
        );
        if ($assets === []) {
            return \sprintf($template, 'no assembly for ' . $os);
        }

        $assets = \array_filter(
            $assets,
            static fn(string $asset): bool => \str_contains($asset, '-' . \strtolower($arch) . '.'),
        );
        if ($assets === []) {
            return \sprintf($template, 'no assembly for ' . $arch);
        }

        return '<fg=green> ✓ </>';
    }

    private static function stabilityToString(string $stability): string
    {
        return match ($stability) {
            Stability::STABILITY_STABLE => "<fg=green> $stability </>",
            Stability::STABILITY_RC => "<fg=blue> $stability </>",
            Stability::STABILITY_BETA => "<fg=yellow> $stability </>",
            Stability::STABILITY_ALPHA => "<fg=red> $stability </>",
            default => "<bg=red;bg=white> $stability </>",
        };
    }
}
