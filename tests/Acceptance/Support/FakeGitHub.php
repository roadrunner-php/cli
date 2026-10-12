<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

/**
 * Runs `Server/router.php` in PHP's built-in web server, one server on a free local port per scenario.
 */
final class FakeGitHub
{
    /** @var array<string, resource> Server processes by scenario. */
    private static array $processes = [];

    /** @var array<string, string> Server URLs by scenario. */
    private static array $servers = [];

    private static string $log = '';
    private static string $output = '';

    public static function start(): void
    {
        if (self::$log !== '') {
            return;
        }

        $dir = \sys_get_temp_dir() . '/rr-cli-acceptance';
        @\mkdir($dir, 0777, true);
        self::$log = \tempnam($dir, 'requests-');
        self::$output = \tempnam($dir, 'server-');
    }

    public static function stop(): void
    {
        foreach (self::$processes as $process) {
            \proc_terminate($process);
            \proc_close($process);
        }
        self::$processes = [];
        self::$servers = [];

        @\unlink(self::$log);
        @\unlink(self::$output);
        self::$log = '';
        self::$output = '';
    }

    /**
     * Base API URL of a scenario, see the router for the list; its server starts on first use.
     */
    public static function url(string $scenario = 'github'): string
    {
        return (self::$servers[$scenario] ??= self::startServer($scenario)) . '/api/v3';
    }

    /**
     * A base API URL nothing listens on.
     */
    public static function unreachableUrl(): string
    {
        return 'http://127.0.0.1:' . self::freePort() . '/api/v3';
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

    /**
     * @return string `http://127.0.0.1:<port>`
     */
    private static function startServer(string $scenario): string
    {
        self::start();
        $port = self::freePort();

        $env = Rr::baseEnvironment();
        $env['FAKE_GITHUB_LOG'] = self::$log;
        $env['FAKE_GITHUB_SCENARIO'] = $scenario;

        $process = \proc_open(
            [\PHP_BINARY, '-S', "127.0.0.1:$port", \dirname(__DIR__) . '/Server/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', self::$output, 'a'], 2 => ['file', self::$output, 'a']],
            $pipes,
            null,
            $env,
        );
        \assert(\is_resource($process));
        \fclose($pipes[0]);

        self::$processes[$scenario] = $process;

        $deadline = \microtime(true) + 10;
        while (($socket = @\fsockopen('127.0.0.1', $port, $errno, $error, 0.2)) === false) {
            if (\microtime(true) > $deadline || ! \proc_get_status($process)['running']) {
                $output = self::serverOutput();
                self::stop();
                throw new \RuntimeException("The fake GitHub server of the $scenario scenario did not start: $output");
            }
            \usleep(50_000);
        }
        \fclose($socket);

        return "http://127.0.0.1:$port";
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
