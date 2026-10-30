<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * `rr get-grpc-tools` with the releases in Server/releases*.json.
 */
#[Test]
final class GrpcToolsTest
{
    use FakeGitHubCase;

    private const ARGS = ['get-grpc-tools', '--os=linux', '--arch=amd64'];

    #[DataSet(['v5.36.2', '^36.0', '36.2'])]
    #[DataSet(['4.33.1', '^33.0', '33.6'])]
    #[DataSet(['v3.21.12', '^21.0 || ~3.21.0', '21.12'], 'first split version')]
    #[DataSet(['v3.20.3', '~3.20.0', '3.20.3'], 'shared version scheme')]
    public function installsProtocOfProjectRuntime(string $runtime, string $constraint, string $protoc): void
    {
        $this->installProtobuf($runtime);

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->contains("google/protobuf $runtime -> protoc $constraint");
        Assert::same(\trim($this->dir->read('protoc')), "fake protoc $protoc linux-x86_64");
        Assert::same(\trim($this->dir->read('buf')), 'fake buf 1.75.0 Linux x86_64');
        Assert::same(\trim($this->dir->read('protoc-gen-php-grpc')), 'fake rr 3.4.0 linux amd64');
        Assert::same(FakeGitHub::downloads(), [
            'buf-Linux-x86_64.tar.gz',
            "protoc-$protoc-linux-x86_64.zip",
            'protoc-gen-php-grpc-3.4.0-linux-amd64.tar.gz',
        ]);
    }

    public function installsWindowsBinaries(): void
    {
        $this->installProtobuf('4.33.1');

        $result = $this->rr()->run(['get-grpc-tools', '--os=windows', '--arch=amd64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('protoc.exe')), 'fake protoc 33.6 win64');
        Assert::same(\trim($this->dir->read('buf.exe')), 'fake buf 1.75.0 Windows x86_64');
        Assert::same(\trim($this->dir->read('protoc-gen-php-grpc.exe')), 'fake rr 3.4.0 windows amd64');
        Assert::same(FakeGitHub::downloads(), [
            'buf-Windows-x86_64.zip',
            'protoc-33.6-win64.zip',
            'protoc-gen-php-grpc-3.4.0-windows-amd64.zip',
        ]);
    }

    public function installsMacosBinaries(): void
    {
        $this->installProtobuf('5.36.2');

        $result = $this->rr()->run(['get-grpc-tools', '--os=darwin', '--arch=arm64']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same(\trim($this->dir->read('buf')), 'fake buf 1.75.0 Darwin arm64');
        Assert::same(\trim($this->dir->read('protoc')), 'fake protoc 36.2 osx-universal_binary');
        Assert::same(\trim($this->dir->read('protoc-gen-php-grpc')), 'fake rr 3.4.0 darwin arm64');
        Assert::same(FakeGitHub::downloads(), [
            'buf-Darwin-arm64.tar.gz',
            'protoc-36.2-osx-universal_binary.zip',
            'protoc-gen-php-grpc-3.4.0-darwin-arm64.tar.gz',
        ]);
    }

    public function installsLatestProtocWithoutRuntime(): void
    {
        self::skipWithExtension();

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->contains('Neither google/protobuf nor ext-protobuf is installed');
        Assert::same(\trim($this->dir->read('protoc')), 'fake protoc 36.2 linux-x86_64');
    }

    public function takesVersionsFromOptions(): void
    {
        $this->installProtobuf('5.36.2');

        $result = $this->rr()->run([...self::ARGS, '--protoc=^33.0', '--buf=*', '--plugin=2025.*']);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->notContains('google/protobuf');
        Assert::same(\trim($this->dir->read('protoc')), 'fake protoc 33.6 linux-x86_64');
        Assert::same(\trim($this->dir->read('buf')), 'fake buf 2.0.0 Linux x86_64');
        Assert::same(\trim($this->dir->read('protoc-gen-php-grpc')), 'fake rr 2025.1.5 linux amd64');
    }

    public function suggestsProtocOptionWhenNoReleaseMatchesRuntime(): void
    {
        $this->installProtobuf('5.40.0');

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 1, (string) $result);
        Assert::string($result->output())->contains('--protoc=<constraint>');
        Assert::false($this->dir->has('protoc'));
        Assert::true($this->dir->has('buf'));
        Assert::true($this->dir->has('protoc-gen-php-grpc'));
    }

    public function replacesExistingBinaryWithoutVersion(): void
    {
        $this->installProtobuf('4.33.1');
        $this->dir->write('protoc', "old protoc\n");

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->notContains('already exists')->contains('gRPC tools are ready');
        Assert::same(\trim($this->dir->read('protoc')), 'fake protoc 33.6 linux-x86_64');
        Assert::same(FakeGitHub::downloads(), [
            'buf-Linux-x86_64.tar.gz',
            'protoc-33.6-linux-x86_64.zip',
            'protoc-gen-php-grpc-3.4.0-linux-amd64.tar.gz',
        ]);
    }

    /**
     * protoc 21.x reports the version of the 3.21.x runtime.
     */
    #[DataSet(['4.33.1', '33.6'])]
    #[DataSet(['3.21.12', '3.21.12'], 'protoc 21')]
    public function keepsExistingBinaryOfMatchingVersion(string $runtime, string $protoc): void
    {
        \PHP_OS_FAMILY === 'Windows' and throw new SkipTest('A shell script stands in for the linux protoc');
        $this->installProtobuf($runtime);
        $script = "#!/bin/sh\necho libprotoc $protoc\n";
        $this->dir->write('protoc', $script);
        \chmod($this->dir->file('protoc'), 0755);

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::same($this->dir->read('protoc'), $script);
        Assert::same(FakeGitHub::downloads(), ['buf-Linux-x86_64.tar.gz', 'protoc-gen-php-grpc-3.4.0-linux-amd64.tar.gz']);
    }

    /**
     * The rr subprocess prefers a loaded ext-protobuf to the google/protobuf of the project.
     */
    private static function skipWithExtension(): void
    {
        \extension_loaded('protobuf') and throw new SkipTest('ext-protobuf is loaded');
    }

    private function installProtobuf(string $version): void
    {
        self::skipWithExtension();
        \mkdir($this->dir->file('vendor/composer'), 0777, true);
        $this->dir->write('vendor/composer/installed.json', \json_encode([
            'packages' => [['name' => 'google/protobuf', 'version' => $version]],
        ]));
    }
}
