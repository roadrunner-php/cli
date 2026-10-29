<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Downloader;

use Internal\DLoad\Command\Get;
use Spiral\RoadRunner\Console\Internal\Environment\Environment;
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
    private const CONFIG = __DIR__ . '/../../../resources/dload.xml';

    /**
     * Not `GITHUB_API_URL`: GitHub Actions sets that one in every job.
     */
    private const ENV_GITHUB_API_URL = 'RR_GITHUB_API_URL';

    private const PUBLIC_API_URL = 'https://api.github.com';

    /**
     * RoadRunner went from calendar versions back to 3.x, and DLoad orders versions numerically,
     * so without this bound `2025.1.x` would win over every 3.x release.
     */
    private const CALENDAR_BOUND = '<2000';

    /**
     * What `*` stands for: 3.x and the later semver lines.
     */
    private const ANY_VERSION = '>=3.0 ' . self::CALENDAR_BOUND;

    /**
     * Software released from the RoadRunner repository, the only one with calendar versions.
     */
    private const ROADRUNNER_SOFTWARE = ['rr', 'protoc-gen-php-grpc'];

    /**
     * DLoad serves the API of a GitHub Enterprise Server at `scheme://host[:port]/api/v3`, so no other path can be mapped.
     */
    private const ENTERPRISE_API_PATH = '/api/v3';

    /**
     * The entries of DLoad's built-in registry this package downloads, with the server `scheme://host[:port]` to substitute.
     *
     * DLoad takes a self-hosted GitHub from the repository `uri` itself: `scheme://host[:port]/owner/repo`.
     */
    private const CONFIG_WITH_SERVER = <<<'XML'
        <?xml version="1.0"?>
        <dload>
            <registry overwrite="false">
                <software name="RoadRunner" alias="rr">
                    <repository type="github" uri="%1$s/roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/"/>
                    <binary name="rr" pattern="/^(roadrunner|rr)(?:\.exe)?$/" version-command="--version"/>
                </software>
                <software name="ProtoC PHP gRPC Plugin" alias="protoc-gen-php-grpc">
                    <repository type="github" uri="%1$s/roadrunner-server/roadrunner" asset-pattern="/^protoc-gen-php-grpc-.*/"/>
                    <binary name="protoc-gen-php-grpc"/>
                </software>
                <software name="Protobuf compiler" alias="protoc">
                    <repository type="github" uri="%1$s/protocolbuffers/protobuf" asset-pattern="/^protoc-.*/"/>
                    <binary name="protoc" version-command="--version"/>
                </software>
                <software name="Buf" alias="buf">
                    <repository type="github" uri="%1$s/bufbuild/buf" asset-pattern="/^buf-.*/"/>
                    <binary name="buf" pattern="/^buf(-.*)?(\.exe)?$/" version-command="--version"/>
                </software>
            </registry>
        </dload>
        XML;

    private readonly Command $get;
    private readonly ?string $githubApiUrl;

    /**
     * @param Command|null $get Runs the download with the input of DLoad's `get` command; DLoad's own command by default.
     * @param string|null $githubApiUrl Base URL of the GitHub API to fetch releases from; the RR_GITHUB_API_URL
     *        environment variable by default, and DLoad's own default when that is not set either.
     */
    public function __construct(?Command $get = null, ?string $githubApiUrl = null)
    {
        /** @psalm-suppress InternalClass DLoad has no public PHP API yet; its `get` command is the stable contract */
        $this->get = $get ?? new Get();
        $this->githubApiUrl = $githubApiUrl ?? Environment::get(self::ENV_GITHUB_API_URL);
    }

    /**
     * @param non-empty-string $software DLoad software alias, e.g. "rr", "protoc-gen-php-grpc", "protoc" or "buf".
     * @param string $constraint Composer version constraint; "*" or empty means any version, which for
     *        the RoadRunner software is 3.x or a later semver line.
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
        $config = $this->createConfig();

        $input = new ArrayInput([
            'software' => [$software . self::versionSuffix(
                $constraint,
                $stability,
                \in_array($software, self::ROADRUNNER_SOFTWARE, true),
            )],
            '--config' => $config ?? self::CONFIG,
            '--path' => $location,
            '--os' => $os,
            '--arch' => $arch,
            '--stability' => $stability,
            '--force' => $force,
            // The version registry cache could hide a release published a moment ago
            '--refresh' => true,
        ]);
        $input->setInteractive(false);

        try {
            return $this->get->run($input, $output);
        } finally {
            if ($config !== null) {
                @\unlink($config);
            }
        }
    }

    /**
     * DLoad ignores `--stability` once a version is given: the stability has to be a part of the constraint.
     *
     * @param bool $calendar Whether the software has calendar releases an unbounded constraint must stay below.
     */
    private static function versionSuffix(string $constraint, string $stability, bool $calendar): string
    {
        [$version, $explicitStability] = \explode('@', \trim($constraint), 2) + [1 => null];
        $version = \trim($version);

        if ($version === '' || $version === '*') {
            $version = $calendar ? self::ANY_VERSION : '*';
        } elseif ($calendar) {
            // DLoad has no parentheses, so the bound goes into each alternative
            $alternatives = \preg_split('/\s*\|\|?\s*/', $version) ?: [$version];
            $version = \implode(' || ', \array_map(self::boundAlternative(...), $alternatives));
        }

        return ':' . $version . '@' . ($explicitStability ?? $stability);
    }

    /**
     * An alternative without an upper bound gets {@see self::CALENDAR_BOUND}, unless its lower bound is a calendar version.
     */
    private static function boundAlternative(string $alternative): string
    {
        $bounded = \preg_match('/<(?!>)|[\^~]|\d\.[*x]|\s-\s/i', $alternative) === 1
            || \preg_match('/^(?:==?)?\s*v?\d[^\s,]*$/i', $alternative) === 1;
        $calendar = \preg_match('/(?<!<)>=?\s*v?(\d+)/', $alternative, $lower) === 1 && (int) $lower[1] >= 2000;

        return $bounded || $calendar ? $alternative : $alternative . ' ' . self::CALENDAR_BOUND;
    }

    /**
     * @return non-empty-string|null `scheme://host[:port]` of a GitHub Enterprise Server, null for the public GitHub.
     */
    private static function server(string $url): ?string
    {
        $parts = \parse_url(\rtrim(\trim($url), '/'));
        $parts = \is_array($parts) ? $parts : [];
        $scheme = \strtolower($parts['scheme'] ?? '');
        $host = \strtolower($parts['host'] ?? '');

        if (! \in_array($scheme, ['http', 'https'], true) || $host === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s value "%s"', self::ENV_GITHUB_API_URL, $url));
        }

        $server = $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = $parts['path'] ?? '';

        if ($server === self::PUBLIC_API_URL && $path === '') {
            return null;
        }

        if ($server === self::PUBLIC_API_URL || $path !== self::ENTERPRISE_API_PATH) {
            throw new \InvalidArgumentException(\sprintf(
                '%s must be %s or a GitHub Enterprise Server API URL ending with %s, "%s" given',
                self::ENV_GITHUB_API_URL,
                self::PUBLIC_API_URL,
                self::ENTERPRISE_API_PATH,
                $url,
            ));
        }

        return $server;
    }

    /**
     * @return string|null Path to a temporary config pointing DLoad at a GitHub Enterprise Server, if one is set.
     */
    private function createConfig(): ?string
    {
        if ($this->githubApiUrl === null || $this->githubApiUrl === '') {
            return null;
        }

        $server = self::server($this->githubApiUrl);
        if ($server === null) {
            return null;
        }

        $file = \tempnam(\sys_get_temp_dir(), 'rr-dload-');
        if ($file === false) {
            throw new \RuntimeException('Can not create a temporary DLoad config');
        }

        \file_put_contents($file, \sprintf(self::CONFIG_WITH_SERVER, \htmlspecialchars($server, \ENT_XML1 | \ENT_QUOTES)));

        return $file;
    }
}
