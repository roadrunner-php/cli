<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Downloader;

use Internal\DLoad\Command\Get;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Downloads binaries through DLoad ({@link https://github.com/php-internal/dload}).
 *
 * @internal
 */
final class DLoadDownloader
{
    /**
     * An empty DLoad config: without it DLoad reads `./dload.xml`, and a `rr` action there
     * would silently replace the version requested via `--filter`.
     */
    private const CONFIG = __DIR__ . '/../../resources/dload.xml';

    private readonly Command $get;

    /**
     * @param Command|null $get Runs the download with the input of DLoad's `get` command; DLoad's own command by default.
     */
    public function __construct(?Command $get = null)
    {
        /** @psalm-suppress InternalClass DLoad has no public PHP API yet; its `get` command is the stable contract */
        $this->get = $get ?? new Get();
    }

    /**
     * @param non-empty-string $software DLoad software alias, e.g. "rr" or "protoc-gen-php-grpc".
     * @param string $constraint Composer version constraint; "*" means any version.
     * @param string $stability Minimum stability, e.g. "stable" or "beta".
     * @param bool $force Overwrite the binary if it already exists in the location.
     *
     * @return int Command exit code.
     */
    public function download(
        string $software,
        string $constraint,
        string $stability,
        string $os,
        string $arch,
        string $location,
        bool $force,
        OutputInterface $output,
    ): int {
        $input = new ArrayInput([
            'software' => [$software . self::versionSuffix($constraint, $stability)],
            '--config' => self::CONFIG,
            '--path' => $location,
            '--os' => $os,
            '--arch' => $arch,
            '--stability' => $stability,
            '--force' => $force,
            // The version registry cache could hide a release published a moment ago
            '--refresh' => true,
        ]);
        $input->setInteractive(false);

        return $this->get->run($input, $output);
    }

    /**
     * DLoad ignores `--stability` once a version is given: the stability has to be a part of the constraint.
     * It also rejects "*", which is the same as no constraint.
     */
    private static function versionSuffix(string $constraint, string $stability): string
    {
        $constraint = \trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return '';
        }

        return ':' . (\str_contains($constraint, '@') ? $constraint : $constraint . '@' . $stability);
    }
}
