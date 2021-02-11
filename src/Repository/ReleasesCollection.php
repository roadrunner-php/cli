<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

/**
 * @template-extends Collection<ReleaseInterface>
 */
final class ReleasesCollection extends Collection
{
    /**
     * @var int[]
     */
    private const STABILITY_WEIGHT = [
        Stability::STABILITY_STABLE => 4,
        Stability::STABILITY_RC     => 3,
        Stability::STABILITY_BETA   => 2,
        Stability::STABILITY_ALPHA  => 1,
        Stability::STABILITY_DEV    => 0,
    ];

    /**
     * @param string ...$constraints
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
     * @param string ...$constraints
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
                \array_map('\\trim', $result)
            )
        );
    }

    /**
     * @return $this
     */
    public function withAssets(): self
    {
        return $this->filter(static fn(ReleaseInterface $r): bool => ! $r->getAssets()
            ->empty()
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
     * @param ReleaseInterface $release
     * @return string
     */
    private function comparisonVersionString(ReleaseInterface $release): string
    {
        $stability = $release->getStability();
        $weight = self::STABILITY_WEIGHT[$stability] ?? 0;

        return \str_replace('-' . $stability, '.' . $weight . '.', $release->getVersion());
    }

    /**
     * @return $this
     */
    public function stable(): self
    {
        return $this->stability(Stability::STABILITY_STABLE);
    }

    /**
     * @param string $stability
     * @return $this
     */
    public function stability(string $stability): self
    {
        $filter = static fn(ReleaseInterface $rel): bool => $rel->getStability() === $stability;

        return $this->filter($filter);
    }
}
