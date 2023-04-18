<?php

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

        $sort = fn(ReleaseInterface $a, ReleaseInterface $b): int => \version_compare($this->comparisonVersionString($b), $this->comparisonVersionString($a));

        \uasort($result, $sort);

        return new self($result);
    }

    private function comparisonVersionString(ReleaseInterface $release): string
    {
        $stability = $release->getStability();
        $weight = Stability::toInt($stability);

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

        return $this->filter(static fn(ReleaseInterface $release): bool => Stability::toInt($release->getStability())
        >= $weight);
    }
}
