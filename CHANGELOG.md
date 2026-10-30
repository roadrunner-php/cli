# Changelog

## [3.1.0](https://github.com/roadrunner-php/cli/compare/3.0.1...3.1.0) (2026-10-30)


### Features

* add get-grpc-tools command ([#76](https://github.com/roadrunner-php/cli/issues/76)) ([e8882ba](https://github.com/roadrunner-php/cli/commit/e8882bae2461bbd4d6b3c24180779397652b6864))
* deprecate download-protoc-binary in favour of get-grpc-tools ([e8882ba](https://github.com/roadrunner-php/cli/commit/e8882bae2461bbd4d6b3c24180779397652b6864))

## [3.0.1](https://github.com/roadrunner-php/cli/compare/3.0.0...3.0.1) (2026-10-19)


### Bug Fixes

* detect the RoadRunner version from roadrunner/roadrunner ([9ebb646](https://github.com/roadrunner-php/cli/commit/9ebb646292f38769ab3317c642f1c110bb91e2e2))

## [3.0.0](https://github.com/roadrunner-php/cli/compare/v2.7.2...3.0.0) (2026-10-12)


### ⚠ BREAKING CHANGES

* generate a configuration that RoadRunner 3 accepts ([#74](https://github.com/roadrunner-php/cli/issues/74))
* the `tcp`, `websockets`, `broadcast` and `reload` plugins are removed: RoadRunner 3 does not ship them.
* the `Command`, `Configuration` and `Downloader` namespaces move to `Spiral\RoadRunner\Console\Internal\Option`, `Internal\Configuration` and `Internal\Downloader`, and `Environment\Stability`, `Environment\RoadRunnerVersion` and `Environment\Environment` to `Internal\Environment`; all of them are internal now. The abstract `Spiral\RoadRunner\Console\Command`, `Command\OptionInterface`, `Environment\Enum` and both `Environment\*\Factory` classes are removed: the commands extend the Symfony command directly. The commands and `Configuration\Generator` are final. `Environment\Environment::get()` takes only the variable name, `OperatingSystem::createFromGlobals()` takes no arguments, `Stability::all()` returns a list, and `RoadRunnerVersion::PACKAGE_NAME` and `DEFAULT_CONSTRAINT` are private.
* the `Spiral\RoadRunner\Console\Archive` and `Spiral\RoadRunner\Console\Repository` namespaces are removed, `Version1\StaticRepository` included. `Command::getRepository()` and `Command::confirm()` are removed, and `VersionFilterOption::find()` and `VersionFilterOption::choices()` with them. `VersionsCommand` is final.
* for `get-binary` and `download-protoc-binary`, `RR_GITHUB_API_URL` must be `https://api.github.com` or end with `/api/v3`, and the token for a GitHub Enterprise Server is read from `DLOAD_TOKEN_<HOST>` (e.g. `DLOAD_TOKEN_GHE_EXAMPLE_COM`) instead of `GITHUB_TOKEN`. `symfony/console` and `symfony/http-client` 6.4 or newer are required.

### Features

* add nsq, kafka, google_pub_sub, centrifuge and protoreg sections ([82f9323](https://github.com/roadrunner-php/cli/commit/82f93236fc8047af3756b799b64dd97c39ac04de))
* download binaries with DLoad ([ca51088](https://github.com/roadrunner-php/cli/commit/ca51088982da763918138e42ea1eb81fec4429fc))
* keep version filters on the RoadRunner 3.x line ([9df5936](https://github.com/roadrunner-php/cli/commit/9df59368ac5ff610b4f143d572c19d0ba225cbc6))
* make the wildcard version filter install RoadRunner 3.0 or newer ([d787366](https://github.com/roadrunner-php/cli/commit/d7873660aaf8b260b67f62db3cba86dd0c2524fd))
* migrate tests to Testo, require PHP 8.2 ([#56](https://github.com/roadrunner-php/cli/issues/56)) ([71c3f7e](https://github.com/roadrunner-php/cli/commit/71c3f7e8d9d004986ada55a1623800395abcdc9a))
* read the GitHub API URL from RR_GITHUB_API_URL ([#65](https://github.com/roadrunner-php/cli/issues/65)) ([eff8b50](https://github.com/roadrunner-php/cli/commit/eff8b5012ead11898d067135a6e5f8eff201d1c6))
* support RoadRunner v3 ([#48](https://github.com/roadrunner-php/cli/issues/48)) ([75a848d](https://github.com/roadrunner-php/cli/commit/75a848d61b138d9ec62a14a22fe76408423c757e))


### Bug Fixes

* do not write .rr.yaml when its generation fails ([ad5bf6d](https://github.com/roadrunner-php/cli/commit/ad5bf6d2a0886ce616fd388f12686df8381ba9ca))
* generate a configuration that RoadRunner 3 accepts ([#74](https://github.com/roadrunner-php/cli/issues/74)) ([82f9323](https://github.com/roadrunner-php/cli/commit/82f93236fc8047af3756b799b64dd97c39ac04de))
* pick the rr versions token like DLoad does ([1592cd4](https://github.com/roadrunner-php/cli/commit/1592cd4b3f0b89bf8769d86cb0536a3d972cec22))


### Documentation

* describe musl selection and DLoad side effects ([7a6563b](https://github.com/roadrunner-php/cli/commit/7a6563bdb6c96001ca66542fae44967998cad737))
* note that 3.x targets RoadRunner v3 ([28ecf5b](https://github.com/roadrunner-php/cli/commit/28ecf5b1018abf562219ac2e0f618d7a55e8ef5c))
* point the empty DLoad config at the moved downloader class ([d38492e](https://github.com/roadrunner-php/cli/commit/d38492ed8722096b783eb2e4bb18431e51a3253d))
* refresh the README ([#53](https://github.com/roadrunner-php/cli/issues/53)) ([623354c](https://github.com/roadrunner-php/cli/commit/623354c2fc7c1230d7d58de087d95e5ff337b94b))


### Code Refactoring

* add #[\Override] and drop the psalm baseline ([#64](https://github.com/roadrunner-php/cli/issues/64)) ([d8a5b3f](https://github.com/roadrunner-php/cli/commit/d8a5b3fa802cacec4d7f64bfab269f4343226c30))
* list the configuration sections without spiral/tokenizer ([58e6301](https://github.com/roadrunner-php/cli/commit/58e6301f1352bbc0e6958ebf023e0441d3f240ce))
* move the implementation into the Internal namespace ([54d3b8a](https://github.com/roadrunner-php/cli/commit/54d3b8a16bb5bfc4417739e555c03ba8cb0ebc4b))
* remove the archive and release repository classes ([f86cecd](https://github.com/roadrunner-php/cli/commit/f86cecdaaf3096bad9da4299c2e0f701a34047f2))
* resolve the Psalm baseline in code ([d8a5b3f](https://github.com/roadrunner-php/cli/commit/d8a5b3fa802cacec4d7f64bfab269f4343226c30))
