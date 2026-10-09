<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

use Spiral\RoadRunner\Console\Environment\Stability;

/**
 * @template-extends Collection<ReleaseInterface>
 * @psalm-import-type StabilityType from Stability
 */
final class ReleasesCollection extends Collection
{
    /**
     * @return $this
     */
    public function satisfies(string ...$constraints): self
    {
        $result = $this;

        foreach ($this->constraints($constraints) as $constraint) {
            $result = $result->filter(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function notSatisfies(string ...$constraints): self
    {
        $result = $this;

        foreach ($this->constraints($constraints) as $constraint) {
            $result = $result->except(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function withAssets(): self
    {
        return $this->filter(
            static fn(ReleaseInterface $r): bool => ! $r->getAssets()
                ->empty(),
        );
    }

    /**
     * @return $this
     */
    public function sortByVersion(): self
    {
        $result = $this->items;

        $sort = function (ReleaseInterface $a, ReleaseInterface $b): int {
            return \version_compare($this->comparisonVersionString($b), $this->comparisonVersionString($a));
        };

        \uasort($result, $sort);

        return new self($result);
    }

    /**
     * @return $this
     */
    public function stable(): self
    {
        return $this->stability(Stability::STABILITY_STABLE);
    }

    /**
     * @param StabilityType $stability
     * @return $this
     */
    public function stability(string $stability): self
    {
        $filter = static fn(ReleaseInterface $rel): bool => $rel->getStability() === $stability;

        return $this->filter($filter);
    }

    /**
     * @param StabilityType $stability
     * @return $this
     */
    public function minimumStability(string $stability): self
    {
        $weight = Stability::toInt($stability);

        return $this->filter(static function (ReleaseInterface $release) use ($weight): bool {
            return Stability::toInt($release->getStability()) >= $weight;
        });
    }

    /**
     * @param array<string> $constraints
     * @return array<string>
     */
    private function constraints(array $constraints): array
    {
        $result = [];

        foreach ($constraints as $constraint) {
            foreach (\explode('|', $constraint) as $expression) {
                $result[] = $expression;
            }
        }

        return \array_unique(
            \array_filter(
                \array_map('\\trim', $result),
            ),
        );
    }

    /**
     * The release name is normalized by composer/semver ("3.0.0-RC1", "3.0.0-beta2"),
     * so version_compare() orders pre-releases below the stable release.
     *
     * RoadRunner went 1.x → 2.x → calendar 2023.x–2025.x → 3.x, so a plain version_compare()
     * would rank 2025.1.15 above 3.0.0. Calendar releases are rewritten as "2.<year>.…" to sort
     * them after every 2.x and before every 3.x release.
     */
    private function comparisonVersionString(ReleaseInterface $release): string
    {
        return (string) \preg_replace('/^(20\d{2}\.)/', '2.$1', $release->getName());
    }
}
