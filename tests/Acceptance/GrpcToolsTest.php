<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Acceptance;

use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHub;
use Spiral\RoadRunner\Console\Tests\Acceptance\Support\FakeGitHubCase;
use Testo\Assert;
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
    }

    public function installsLatestProtocWithoutRuntime(): void
    {
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

    public function keepsExistingBinariesWithoutInteraction(): void
    {
        $this->dir->write('protoc', "old protoc\n");

        $result = $this->rr()->run(self::ARGS);

        Assert::same($result->exitCode, 0, (string) $result);
        Assert::string($result->output())->contains('protoc binary file already exists!');
        Assert::same($this->dir->read('protoc'), "old protoc\n");
        Assert::same(FakeGitHub::downloads(), ['buf-Linux-x86_64.tar.gz', 'protoc-gen-php-grpc-3.4.0-linux-amd64.tar.gz']);
    }

    private function installProtobuf(string $version): void
    {
        \mkdir($this->dir->file('vendor/composer'), 0777, true);
        $this->dir->write('vendor/composer/installed.php', \sprintf(
            "<?php return ['root' => [], 'versions' => ['google/protobuf' => ['pretty_version' => %s]]];",
            \var_export($version, true),
        ));
    }
}
