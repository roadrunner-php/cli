<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

/**
 * Runs `Server/router.php` in PHP's built-in web server on a free local port.
 */
final class FakeGitHub
{
    /** @var resource|null */
    private static $process = null;

    private static string $url = '';
    private static string $log = '';
    private static string $output = '';

    public static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $port = self::freePort();
        $dir = \sys_get_temp_dir() . '/rr-cli-acceptance';
        @\mkdir($dir, 0777, true);
        self::$log = \tempnam($dir, 'requests-');
        self::$output = \tempnam($dir, 'server-');

        $env = Rr::baseEnvironment();
        $env['FAKE_GITHUB_LOG'] = self::$log;

        $process = \proc_open(
            [\PHP_BINARY, '-S', "127.0.0.1:$port", \dirname(__DIR__) . '/Server/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', self::$output, 'a'], 2 => ['file', self::$output, 'a']],
            $pipes,
            null,
            $env,
        );
        \assert(\is_resource($process));
        \fclose($pipes[0]);

        self::$process = $process;
        self::$url = "http://127.0.0.1:$port";

        $deadline = \microtime(true) + 10;
        while (($socket = @\fsockopen('127.0.0.1', $port, $errno, $error, 0.2)) === false) {
            if (\microtime(true) > $deadline || ! \proc_get_status($process)['running']) {
                self::stop();
                throw new \RuntimeException('The fake GitHub server did not start: ' . self::serverOutput());
            }
            \usleep(50_000);
        }
        \fclose($socket);
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            \proc_terminate(self::$process);
            \proc_close(self::$process);
            self::$process = null;
        }

        @\unlink(self::$log);
        @\unlink(self::$output);
    }

    /**
     * Base API URL of a scenario, see the router for the list.
     */
    public static function url(string $scenario = 'github'): string
    {
        return self::$url . '/' . $scenario;
    }

    /**
     * A base URL nothing listens on.
     */
    public static function unreachableUrl(): string
    {
        return 'http://127.0.0.1:' . self::freePort() . '/github';
    }

    public static function resetRequests(): void
    {
        \file_put_contents(self::$log, '');
    }

    /**
     * @return list<array{method: string, path: string, query: array, status: int, authorized: bool}>
     */
    public static function requests(): array
    {
        $lines = \file(self::$log, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [];

        return \array_map(static fn(string $line): array => \json_decode($line, true), $lines);
    }

    /**
     * @return list<array{method: string, path: string, query: array, status: int, authorized: bool}>
     */
    public static function releaseRequests(): array
    {
        return \array_values(\array_filter(
            self::requests(),
            static fn(array $request): bool => \str_ends_with($request['path'], '/releases'),
        ));
    }

    /**
     * @return list<string> Names of the downloaded assets.
     */
    public static function downloads(): array
    {
        $names = [];
        foreach (self::requests() as $request) {
            if (\str_contains($request['path'], '/download/')) {
                $names[] = \basename($request['path']);
            }
        }

        return $names;
    }

    private static function serverOutput(): string
    {
        return (string) @\file_get_contents(self::$output);
    }

    private static function freePort(): int
    {
        $server = \stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        \assert($server !== false, $error);
        $name = (string) \stream_socket_get_name($server, false);
        \fclose($server);

        return (int) \substr($name, \strrpos($name, ':') + 1);
    }
}
