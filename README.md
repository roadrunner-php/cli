<p align="center">
    <a href="https://roadrunner.dev"><picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
        <img alt="RoadRunner" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8" style="width: 6in; display: block">
    </picture></a>
</p>

<p align="center">Command line tool to install RoadRunner binaries and generate configs</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://docs.roadrunner.dev)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/roadrunner-server)

[![Psalm Level](https://shepherd.dev/github/roadrunner-php/cli/level.svg)](https://shepherd.dev/github/roadrunner-php/cli)
[![Type Coverage](https://shepherd.dev/github/roadrunner-php/cli/coverage.svg)](https://shepherd.dev/github/roadrunner-php/cli)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Froadrunner-php%2Fcli%2F3.x)](https://dashboard.stryker-mutator.io/reports/github.com/roadrunner-php/cli/3.x)

</div>

<br />

This package provides the `rr` console command for PHP projects that run on [RoadRunner](https://roadrunner.dev): it downloads the RoadRunner server binary and `protoc-gen-php-grpc` plugin built for your environment and generates a starter `.rr.yaml` configuration.

> [!NOTE]
> Version 3.x of `roadrunner/cli` targets RoadRunner v3: `rr get` downloads RoadRunner 3 by default. For RoadRunner 2025.x, use [`spiral/roadrunner-cli`](https://packagist.org/packages/spiral/roadrunner-cli) 2.x.

## Get Started

### Installation

```bash
composer require roadrunner/cli
```

[![PHP](https://img.shields.io/packagist/php-v/roadrunner/cli.svg?style=flat-square&logo=php)](https://packagist.org/packages/roadrunner/cli)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/roadrunner/cli.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/roadrunner/cli)
[![License](https://img.shields.io/packagist/l/roadrunner/cli.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/roadrunner/cli.svg?style=flat-square)](https://packagist.org/packages/roadrunner/cli/stats)

### Getting the RoadRunner binary

Download the latest RoadRunner binary for your operating system and architecture into the project root, along with an example `.rr.yaml`:

```bash
vendor/bin/rr get-binary
```

Then start the server:

```bash
./rr serve
```

See the [RoadRunner documentation](https://docs.roadrunner.dev) for the configuration reference.

## Commands

- `get-binary` (or `get`) - allows to install the latest version of the RoadRunner compatible with
  your environment (operating system, processor architecture, runtime, etc...).
  Also, this command creates an example `.rr.yaml` configuration file. If the command is used without the
  `plugin` and `preset` options, a configuration with the default plugins (`rpc`, `server`, `http`, `jobs`, `kv`, `metrics`) is created.
  Using the `plugin` option (shortcut `p`) can create an example configuration file with only plugins needed.
  For example, with http plugin only: `get-binary -p http`, http and jobs: `get-binary -p http -p jobs`.
  Available plugins: `amqp`, `beanstalk`, `boltdb`, `centrifuge`, `endure`, `fileserver`, `google_pub_sub`, `grpc`,
  `http`, `jobs`, `kafka`, `kv`, `logs`, `metrics`, `nats`, `nsq`, `otel`, `protoreg`, `redis`, `rpc`, `server`,
  `service`, `sqs`, `status`, `temporal`.
  Using the `preset` option can create an example configuration file with popular plugins for different typical tasks.
  For example, with web preset: `get-binary --preset web`.
  Available presets: `web` (contains plugins `http`, `jobs`).
  Use `--no-config` to skip the configuration file.
- `make-config` - creates the `.rr.yaml` configuration file without downloading the binary. Accepts the same `plugin` and `preset` options.
- `download-protoc-binary` - allows to install the latest version of the `protoc-gen-php-grpc` file compatible with
  your environment (operating system, processor architecture, runtime, etc...).
- `versions` - displays a list of available RoadRunner binary versions.

### Common options

The binary commands accept `--filter` (`-f`, version constraint; for `get-binary` and `download-protoc-binary` `*` means `>=3.0 <2000` and any other `||` alternative without an upper bound gets `<2000`, so the calendar releases have to be named explicitly, e.g. `2025.*` or `>=2025`), `--stability` (`-s`), `--os` (`-o`), `--arch` (`-a`) and `--location` (`-l`, target directory) options. `get-binary` and `download-protoc-binary` download the binaries with [DLoad](https://github.com/php-internal/dload); a project's `dload.xml` does not affect them.

On a musl host such as Alpine they install the musl build of Linux on their own, even with `--os=linux`; `--os=unknown-musl` forces the musl build from any host.

DLoad comes with its own `vendor/bin/dload` and keeps a version registry in the user cache: `~/.cache/dload`, `$XDG_CACHE_HOME/dload` when that is set, `%LOCALAPPDATA%\dload\cache` on Windows. `DLOAD_CACHE_DIR` moves it, `DLOAD_CACHE_TTL=0` turns it off.

Releases are fetched from the GitHub API at `RR_GITHUB_API_URL`, `https://api.github.com` by default. For `get-binary` and `download-protoc-binary` it is either `https://api.github.com` or a GitHub Enterprise Server API URL ending with `/api/v3` (e.g. `https://ghe.example.com/api/v3`); `versions` also accepts a mirror.

All commands pick the API token the same way, to avoid the rate limits:

- the public GitHub gets `DLOAD_TOKEN_GITHUB_COM`, or `GITHUB_TOKEN` when that one is not set;
- any other server gets only its own `DLOAD_TOKEN_<HOST>`: the host upper-cased, `.` as `_`, `-` as `__`, a non-default port after `___` (e.g. `DLOAD_TOKEN_GHE_EXAMPLE_COM`, `DLOAD_TOKEN_GHE_EXAMPLE_COM___8443`); `GITHUB_TOKEN` never leaves the public GitHub;
- over plain `http` a token is sent only to a loopback host, and it is never carried over a redirect to another host.
