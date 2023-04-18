<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

/**
 * @template-extends Collection<AssetInterface>
 */
final class AssetsCollection extends Collection
{
    /**
     * @return $this
     */
    public function onlyRoadrunner(): self
    {
        return $this->filter(static fn (AssetInterface $asset): bool =>
            \str_starts_with($asset->getName(), 'roadrunner')
        );
    }

    /**
     * @return $this
     */
    public function exceptDebPackages(): self
    {
        return $this->except(static fn (AssetInterface $asset): bool =>
            \str_ends_with(\strtolower($asset->getName()), '.deb')
        );
    }

    /**
     * @return $this
     */
    public function whereArchitecture(string $arch): self
    {
        return $this->filter(static fn (AssetInterface $asset): bool =>
            \str_contains($asset->getName(), '-' . \strtolower($arch) . '.')
        );
    }

    /**
     * @return $this
     */
    public function whereOperatingSystem(string $os): self
    {
        return $this->filter(static fn (AssetInterface $asset): bool =>
            \str_contains($asset->getName(), '-' . \strtolower($os) . '-')
        );
    }
}
