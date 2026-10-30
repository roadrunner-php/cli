<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Installation;

use Symfony\Component\Console\Style\StyleInterface;

/**
 * Asks before a command overwrites a binary that is already installed.
 *
 * @internal
 */
final class ExistingBinary
{
    /**
     * @param string $binary Path to the binary file.
     * @param string $name Name of the software shown to the user.
     * @return bool Whether the binary may be installed: it is absent or the user agreed to overwrite it.
     */
    public static function mayInstall(string $binary, string $name, StyleInterface $io): bool
    {
        if (! \is_file($binary)) {
            return true;
        }

        $io->warning(\sprintf('%s binary file already exists!', $name));

        if ($io->confirm('Do you want overwrite it?', false)) {
            return true;
        }

        $io->note(\sprintf('Skipping %s installation...', $name));

        return false;
    }
}
