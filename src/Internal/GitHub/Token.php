<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\GitHub;

use Spiral\RoadRunner\Console\Internal\Environment\Environment;

/**
 * Picks the GitHub API token for an API URL by the rules of DLoad, so `rr versions` sends a token
 * to the same hosts `rr get-binary` does.
 *
 * The rules repeat DLoad's `Internal\DLoad\Module\Repository\Internal\Server::tokenVariable()` and
 * `isSecure()`, and `Internal\DLoad\Module\Repository\Internal\GitHub\Factory::token()`: DLoad has
 * no public API for them.
 *
 * @internal
 */
final class Token
{
    private const PUBLIC_API_HOST = 'api.github.com';

    /**
     * The variable of the public GitHub, it wins over GITHUB_TOKEN.
     */
    private const PUBLIC_VARIABLE = 'DLOAD_TOKEN_GITHUB_COM';

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * Dot-separated labels of letters, digits and inner hyphens: with no hyphen at either end of a label,
     * distinct hosts never map to the same variable name.
     */
    private const HOSTNAME = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/';

    /**
     * @return non-empty-string|null The token, null when none is declared for the host or the host may not receive one.
     */
    public static function forApiUrl(string $apiUrl): ?string
    {
        $parts = \parse_url(\trim($apiUrl));
        if (! \is_array($parts) || ! isset($parts['host'], $parts['scheme'])) {
            return null;
        }

        $scheme = \strtolower($parts['scheme']);
        $host = \strtolower($parts['host']);
        if (! isset(self::DEFAULT_PORTS[$scheme])) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $port === self::DEFAULT_PORTS[$scheme] and $port = null;

        if ($scheme === 'https' && $host === self::PUBLIC_API_HOST && $port === null) {
            return self::env(self::PUBLIC_VARIABLE) ?? self::env('GITHUB_TOKEN');
        }

        // Plain HTTP would expose the token on the wire, unless the traffic never leaves the machine
        if ($scheme === 'http' && ! self::isLoopback($host)) {
            return null;
        }

        $variable = self::variable($host, $port);

        return $variable === null ? null : self::env($variable);
    }

    /**
     * `ghe.my-corp.com:8443` gives `DLOAD_TOKEN_GHE_MY__CORP_COM___8443`, `[::1]` gives `DLOAD_TOKEN__0_0_0_0_0_0_0_1`.
     *
     * @return non-empty-string|null Null for a malformed host.
     */
    private static function variable(string $host, ?int $port): ?string
    {
        if (\str_starts_with($host, '[')) {
            $address = \filter_var(\substr($host, 1, -1), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6);
            if ($address === false) {
                return null;
            }

            /** @var array<int, int> $groups */
            $groups = \unpack('n8', (string) \inet_pton($address));
            $name = '_' . \implode('_', \array_map(\dechex(...), $groups));
        } elseif (\preg_match(self::HOSTNAME, $host) === 1) {
            $name = \strtr($host, ['.' => '_', '-' => '__']);
        } else {
            return null;
        }

        return \strtoupper('DLOAD_TOKEN_' . $name . ($port === null ? '' : '___' . $port));
    }

    private static function isLoopback(string $host): bool
    {
        return $host === 'localhost'
            || $host === '[::1]'
            || \str_ends_with($host, '.localhost')
            || \preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host) === 1;
    }

    /**
     * @return non-empty-string|null
     */
    private static function env(string $name): ?string
    {
        $value = Environment::get($name);

        return $value === null || $value === '' ? null : $value;
    }
}
