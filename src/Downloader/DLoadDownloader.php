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
use Spiral\RoadRunner\Console\Environment\Environment;
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

    /**
     * Not `GITHUB_API_URL`: GitHub Actions sets that one in every job.
     */
    private const ENV_GITHUB_API_URL = 'RR_GITHUB_API_URL';

    /**
     * The RoadRunner entries of DLoad's built-in registry, with the API host to substitute.
     */
    private const CONFIG_WITH_HOST = <<<'XML'
        <?xml version="1.0"?>
        <dload>
            <registry overwrite="false">
                <software name="RoadRunner" alias="rr">
                    <repository type="github" uri="roadrunner-server/roadrunner" host="%1$s" asset-pattern="/^roadrunner-.*/"/>
                    <binary name="rr" pattern="/^(roadrunner|rr)(?:\.exe)?$/" version-command="--version"/>
                </software>
                <software name="ProtoC PHP gRPC Plugin" alias="protoc-gen-php-grpc">
                    <repository type="github" uri="roadrunner-server/roadrunner" host="%1$s" asset-pattern="/^protoc-gen-php-grpc-.*/"/>
                    <binary name="protoc-gen-php-grpc"/>
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
        $config = $this->createConfig();

        $input = new ArrayInput([
            'software' => [$software . self::versionSuffix($constraint, $stability)],
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

    /**
     * DLoad takes a bare host name for `repository.host`: the scheme and the path of the URL are dropped,
     * a port is kept.
     *
     * @return non-empty-string
     */
    private static function host(string $url): string
    {
        $parts = \parse_url(\str_contains($url, '://') ? $url : 'https://' . $url);
        $host = \is_array($parts) ? ($parts['host'] ?? '') : '';

        if ($host === '') {
            throw new \InvalidArgumentException(\sprintf('Invalid %s value "%s"', self::ENV_GITHUB_API_URL, $url));
        }

        return isset($parts['port']) ? $host . ':' . $parts['port'] : $host;
    }

    /**
     * @return string|null Path to a temporary config pointing DLoad at the GitHub API host, if one is set.
     */
    private function createConfig(): ?string
    {
        if ($this->githubApiUrl === null || $this->githubApiUrl === '') {
            return null;
        }

        $host = \htmlspecialchars(self::host($this->githubApiUrl), \ENT_XML1 | \ENT_QUOTES);

        $file = \tempnam(\sys_get_temp_dir(), 'rr-dload-');
        if ($file === false) {
            throw new \RuntimeException('Can not create a temporary DLoad config');
        }

        \file_put_contents($file, \sprintf(self::CONFIG_WITH_HOST, $host));

        return $file;
    }
}
