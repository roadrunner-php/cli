<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\GitHub;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * A RoadRunner release as `rr versions` shows it.
 *
 * @internal
 */
final class Release
{
    /**
     * @param string $name Version normalized by composer/semver without the fourth number: "3.5.0-RC1", "dev-nightly".
     * @param string $stability One of the composer/semver stabilities: "stable", "RC", "beta", "alpha" or "dev".
     * @param list<string> $assets File names of the release assets.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $stability,
        public readonly array $assets = [],
    ) {}

    /**
     * @param array<array-key, mixed> $release An item of the GitHub "List releases" response.
     */
    public static function fromApiResponse(array $release): self
    {
        $tag = self::string($release['tag_name'] ?? null);
        if ($tag === null || $tag === '') {
            throw new \UnexpectedValueException('A GitHub release must have a "tag_name" string');
        }

        $title = self::string($release['name'] ?? null);
        $name = self::normalize($tag) ?? ($title === null ? null : self::normalize($title)) ?? 'dev-' . $tag;

        $assets = [];
        /** @var mixed $asset */
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            $assetName = \is_array($asset) ? self::string($asset['name'] ?? null) : null;
            if ($assetName !== null) {
                $assets[] = $assetName;
            }
        }

        return new self($name, VersionParser::parseStability($name), $assets);
    }

    /**
     * Orders releases from the newest to the oldest.
     *
     * RoadRunner went 1.x → 2.x → calendar 2023.x–2025.x → 3.x, so calendar releases are compared
     * as "2.<year>.…": after every 2.x and before every 3.x release.
     */
    public static function newestFirst(self $a, self $b): int
    {
        $key = static fn(self $release): string => (string) \preg_replace('/^(20\d{2}\.)/', '2.$1', $release->name);

        return \version_compare($key($b), $key($a));
    }

    public function satisfies(string $constraint): bool
    {
        return Semver::satisfies($this->name, $constraint);
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * @return non-empty-string|null "3.5.0-RC1" for "v3.5.0-rc.1", null when the value is not a version.
     */
    private static function normalize(string $version): ?string
    {
        try {
            $normalized = (new VersionParser())->normalize($version);
        } catch (\UnexpectedValueException) {
            return null;
        }

        /** @var non-empty-string */
        return \preg_replace('/^(\d+\.\d+\.\d+)\.0(?=-|$)/', '$1', $normalized);
    }
}
