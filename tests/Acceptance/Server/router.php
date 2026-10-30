<?php

/**
 * A fake GitHub for the acceptance tests, run as `php -S 127.0.0.1:<port> router.php`.
 *
 * A server plays one scenario, named by the FAKE_GITHUB_SCENARIO environment variable: DLoad takes a GitHub
 * Enterprise Server from the repository uri `scheme://host:port/owner/repo`, so a scenario can not be a part of the path.
 *  - `github`        — releases of RoadRunner from releases.json, of protobuf from releases-protobuf.json and of buf
 *                      from releases-buf.json (its v2.0.0 is made up), paginated by `per_page` like GitHub
 *                      (30 by default, 100 at most);
 *  - `paged`         — the same releases, 2 per page whatever `per_page` asks for;
 *  - `rate-limit`    — every API request fails with the 403 GitHub sends when the rate limit is exhausted;
 *  - `missing-asset` — releases are listed, but every download is a 404;
 *  - `redirect`      — every request is redirected to the same path of the server named by FAKE_GITHUB_REDIRECT_TO.
 *
 * Like GitHub Enterprise Server, the API is served under `/api/v3`: release lists at
 * `/api/v3/repos/<owner>/<repo>/releases`, assets at
 * `/download/<tag>/<asset name>`. Archives are built on request: `roadrunner-<v>-<os>-<arch>/rr[.exe]`
 * holding the text `fake rr <v> <os> <arch>`, `bin/protoc[.exe]` holding `fake protoc <v> <platform>` and
 * `buf/bin/buf[.exe]` holding `fake buf <v> <os> <arch>`, so a test can tell which asset ended up installed.
 *
 * Each request is appended to the JSON Lines file named by the FAKE_GITHUB_LOG environment variable.
 *
 * No named functions or classes here: Testo includes files that declare functions while it looks for tests.
 */

declare(strict_types=1);

$path = (string) \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
\parse_str((string) \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_QUERY), $query);
$segments = \explode('/', \trim($path, '/'));
$scenario = (string) \getenv('FAKE_GITHUB_SCENARIO');
$server = 'http://' . $_SERVER['HTTP_HOST'];
$api = $server . '/api/v3';

$respond = static function (int $status, string $body, array $headers = []) use ($path, $query): void {
    $log = \getenv('FAKE_GITHUB_LOG');
    if (\is_string($log) && $log !== '') {
        $entry = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'path' => $path,
            'query' => $query,
            'status' => $status,
            'authorized' => isset($_SERVER['HTTP_AUTHORIZATION']),
        ];
        \file_put_contents($log, \json_encode($entry, \JSON_UNESCAPED_SLASHES) . "\n", \FILE_APPEND | \LOCK_EX);
    }

    \http_response_code($status);
    foreach ($headers as $name => $value) {
        \header($name . ': ' . $value);
    }
    echo $body;
};

$archive = static function (string $name, string $tag): ?string {
    $files = [];
    $pattern = '/^(roadrunner|protoc-gen-php-grpc)-(.+)-(linux|darwin|freebsd|windows)-(amd64|arm64)\.(tar\.gz|zip)$/';
    if (\preg_match($pattern, $name, $m) === 1) {
        [, $software, $version, $os, $arch, $format] = $m;
        $binary = ($software === 'roadrunner' ? 'rr' : $software) . ($os === 'windows' ? '.exe' : '');
        $root = "$software-$version-$os-$arch";
        $files = ["$root/$binary" => "fake rr $version $os $arch\n", "$root/LICENSE" => "MIT\n"];
    } elseif (\preg_match('/^protoc-(.+?)-((?:linux|osx)-[a-z0-9_]+|win32|win64)\.(zip)$/', $name, $m) === 1) {
        [, $version, $platform, $format] = $m;
        $binary = 'protoc' . (\str_starts_with($platform, 'win') ? '.exe' : '');
        $files = ["bin/$binary" => "fake protoc $version $platform\n", 'include/google/protobuf/empty.proto' => "\n"];
    } elseif (\preg_match('/^buf-(Linux|Darwin|Windows)-([a-z0-9_]+)\.(tar\.gz|zip)$/', $name, $m) === 1) {
        [, $os, $arch, $format] = $m;
        $version = \ltrim($tag, 'v');
        $binary = 'buf' . ($os === 'Windows' ? '.exe' : '');
        $files = ["buf/bin/$binary" => "fake buf $version $os $arch\n", 'buf/LICENSE' => "Apache-2.0\n"];
    } else {
        return null;
    }

    $dir = \sys_get_temp_dir() . '/rr-cli-fake-github';
    @\mkdir($dir, 0777, true);
    $file = $dir . '/archive-' . \bin2hex(\random_bytes(8)) . ($format === 'zip' ? '.zip' : '.tar');

    $phar = new \PharData($file);
    foreach ($files as $entry => $content) {
        $phar->addFromString($entry, $content);
    }
    if ($format === 'tar.gz') {
        $phar->compress(\Phar::GZ);
    }
    unset($phar);

    $result = $format === 'zip' ? $file : $file . '.gz';
    $content = (string) \file_get_contents($result);
    @\unlink($file);
    @\unlink($file . '.gz');

    return $content;
};

if ($scenario === 'redirect') {
    $respond(302, '', ['Location' => \getenv('FAKE_GITHUB_REDIRECT_TO') . $_SERVER['REQUEST_URI']]);

    return true;
}

if ($scenario === 'rate-limit') {
    $respond(403, \json_encode([
        'message' => 'API rate limit exceeded for 127.0.0.1. (But here\'s the good news: Authenticated requests get a higher rate limit.)',
        'documentation_url' => 'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
    ]), [
        'Content-Type' => 'application/json; charset=utf-8',
        'X-RateLimit-Limit' => '60',
        'X-RateLimit-Remaining' => '0',
        'X-RateLimit-Reset' => (string) (\time() + 3600),
    ]);

    return true;
}

$repository = \preg_match('#^api/v3/repos/([^/]+/[^/]+)/releases$#', \implode('/', $segments), $m) === 1 ? $m[1] : '';
$releaseFile = [
    'roadrunner-server/roadrunner' => 'releases.json',
    'protocolbuffers/protobuf' => 'releases-protobuf.json',
    'bufbuild/buf' => 'releases-buf.json',
][$repository] ?? null;

if ($releaseFile !== null) {
    $releases = \json_decode((string) \file_get_contents(__DIR__ . '/' . $releaseFile), true);
    $perPage = $scenario === 'paged' ? 2 : \min(100, \max(1, (int) ($query['per_page'] ?? 30)));
    $page = \max(1, (int) ($query['page'] ?? 1));
    $last = (int) \ceil(\count($releases) / $perPage);

    $body = [];
    foreach (\array_slice($releases, ($page - 1) * $perPage, $perPage) as $release) {
        $tag = $release['tag_name'];
        $body[] = [
            'tag_name' => $tag,
            'name' => $tag,
            'draft' => false,
            'prerelease' => $release['prerelease'],
            'published_at' => '2026-01-01T00:00:00Z',
            'assets' => \array_map(static fn(string $asset): array => [
                'name' => $asset,
                'browser_download_url' => "$server/download/$tag/$asset",
                // Archives are built on request, so their real size is unknown here
                'size' => 0,
                'content_type' => \str_ends_with($asset, '.zip') ? 'application/zip' : 'application/gzip',
            ], $release['assets']),
        ];
    }

    $link = static fn(int $to, string $rel): string => \sprintf(
        '<%s/repos/%s/releases?per_page=%d&page=%d>; rel="%s"',
        $api,
        $repository,
        $perPage,
        $to,
        $rel,
    );
    $links = [];
    if ($page > 1) {
        $links[] = $link($page - 1, 'prev');
        $links[] = $link(1, 'first');
    }
    if ($page < $last) {
        $links[] = $link($page + 1, 'next');
        $links[] = $link($last, 'last');
    }

    $respond(200, \json_encode($body, \JSON_UNESCAPED_SLASHES), \array_filter([
        'Content-Type' => 'application/json; charset=utf-8',
        'Link' => \implode(', ', $links),
    ]));

    return true;
}

if (($segments[0] ?? '') === 'download' && \count($segments) === 3 && $scenario !== 'missing-asset') {
    $content = $archive($segments[2], $segments[1]) ?? \str_repeat("\0", 1024);
    $respond(200, $content, [
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => (string) \strlen($content),
    ]);

    return true;
}

$respond(404, '{"message":"Not Found"}', ['Content-Type' => 'application/json; charset=utf-8']);

return true;
