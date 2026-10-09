<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance\Support;

/**
 * Runs `bin/rr` in a separate process, in its own working directory and with a clean environment.
 */
final class Rr
{
    private const TIMEOUT = 120;

    /**
     * Variables a process needs to start at all; everything else, GITHUB_TOKEN included, is dropped.
     */
    private const INHERITED = ['PATH', 'Path', 'SystemRoot', 'SYSTEMROOT', 'windir', 'TEMP', 'TMP', 'TMPDIR', 'HOME', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];

    /** @var array<string, string> */
    private array $env;

    /**
     * @param string|null $apiUrl Value of RR_GITHUB_API_URL; null leaves the real GitHub API.
     */
    public function __construct(
        public readonly string $workdir,
        ?string $apiUrl,
    ) {
        $this->env = self::baseEnvironment() + [
            'DLOAD_CACHE_DIR' => $workdir . '/.dload-cache',
            // A fixed width keeps Symfony Console from wrapping messages at the terminal width
            'COLUMNS' => '300',
        ];

        if ($apiUrl !== null) {
            $this->env['RR_GITHUB_API_URL'] = $apiUrl;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function baseEnvironment(): array
    {
        $env = [];
        foreach (self::INHERITED as $name) {
            $value = \getenv($name);
            if (\is_string($value)) {
                $env[$name] = $value;
            }
        }

        return $env;
    }

    public function withEnv(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->env[$name] = $value;

        return $clone;
    }

    /**
     * @param list<string> $args
     * @param list<string>|null $answers Lines typed into an interactive session; null runs with `--no-interaction`.
     */
    public function run(array $args, ?array $answers = null): Result
    {
        $env = $this->env;
        if ($answers === null) {
            $args[] = '--no-interaction';
        } else {
            // Symfony Console turns interaction off when stdin is not a TTY, unless this is set
            $env['SHELL_INTERACTIVE'] = '1';
        }

        $stdout = (string) \tempnam(\sys_get_temp_dir(), 'rr-out-');
        $stderr = (string) \tempnam(\sys_get_temp_dir(), 'rr-err-');

        $process = \proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 3) . '/bin/rr', ...$args],
            [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']],
            $pipes,
            $this->workdir,
            $env,
        );
        \assert(\is_resource($process));

        \fwrite($pipes[0], $answers === null ? '' : \implode(\PHP_EOL, $answers) . \PHP_EOL);
        \fclose($pipes[0]);

        $deadline = \microtime(true) + self::TIMEOUT;
        while (($status = \proc_get_status($process))['running']) {
            if (\microtime(true) > $deadline) {
                \proc_terminate($process);
                \proc_close($process);
                throw new \RuntimeException(\sprintf('rr %s timed out', \implode(' ', $args)));
            }
            \usleep(20_000);
        }
        \proc_close($process);

        $result = new Result(
            $status['exitcode'],
            (string) \file_get_contents($stdout),
            (string) \file_get_contents($stderr),
        );
        @\unlink($stdout);
        @\unlink($stderr);

        return $result;
    }
}
