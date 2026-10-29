<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Environment;

use Composer\InstalledVersions;
use Spiral\RoadRunner\Console\Internal\Environment\ProtobufRuntime;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\TempDirectory;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class ProtobufRuntimeTest
{
    private string $dir;
    private ?bool $canGetVendors = null;
    private array $server = [];

    /**
     * The PHP runtime minor is the protoc major since protobuf 21; up to 3.20 both shared one version.
     */
    #[DataSet(['3.20.3', '~3.20.0'], 'last shared version')]
    #[DataSet(['3.6.1', '~3.6.0'], 'old shared version')]
    #[DataSet(['3.21.12', '^21.0'], 'first split version')]
    #[DataSet(['3.25.5', '^25.0'], 'last 3.x runtime')]
    #[DataSet(['4.26.0', '^26.0'], 'first 4.x runtime')]
    #[DataSet(['4.33.1', '^33.0'])]
    #[DataSet(['v4.33.1', '^33.0'], 'v prefix')]
    #[DataSet(['5.36.2', '^36.0'], '5.x runtime')]
    #[DataSet(['5.37.0RC1', '^37.0'], 'extension pre-release')]
    #[DataSet(['4.33.x-dev', '^33.0'], 'release branch')]
    #[DataSet(['dev-main', null], 'branch')]
    #[DataSet(['', null], 'empty')]
    #[DataSet(['latest', null], 'garbage')]
    #[DataSet(['2.6.1', null], 'protobuf 2')]
    public function derivesProtocConstraint(string $version, ?string $constraint): void
    {
        $runtime = new ProtobufRuntime(ProtobufRuntime::PACKAGE, $version);

        Assert::same($runtime->protocConstraint(), $constraint);
    }

    public function readsVersionFromProjectInstallation(): void
    {
        $this->installed('vendor', ['google/protobuf' => ['pretty_version' => 'v5.36.2']]);

        $runtime = ProtobufRuntime::fromProject($this->dir);

        Assert::same($runtime?->name, 'google/protobuf');
        Assert::same($runtime?->version, 'v5.36.2');
    }

    public function honoursVendorDirOfProject(): void
    {
        \file_put_contents($this->dir . '/composer.json', '{"config": {"vendor-dir": "lib/vendor"}}');
        $this->installed('vendor', ['google/protobuf' => ['pretty_version' => '4.26.0']]);
        $this->installed('lib/vendor', ['google/protobuf' => ['pretty_version' => '4.33.1']]);

        Assert::same(ProtobufRuntime::fromProject($this->dir)?->version, '4.33.1');
    }

    public function honoursComposerVendorDirVariable(): void
    {
        $this->installed('deps', ['google/protobuf' => ['pretty_version' => '4.33.1']]);
        $_SERVER['COMPOSER_VENDOR_DIR'] = 'deps';

        try {
            $runtime = ProtobufRuntime::fromProject($this->dir);
        } finally {
            unset($_SERVER['COMPOSER_VENDOR_DIR']);
        }

        Assert::same($runtime?->version, '4.33.1');
    }

    public function honoursComposerVariable(): void
    {
        \file_put_contents($this->dir . '/app.json', '{"config": {"vendor-dir": "deps"}}');
        $this->installed('deps', ['google/protobuf' => ['pretty_version' => '4.33.1']]);
        $_SERVER['COMPOSER'] = 'app.json';

        Assert::same(ProtobufRuntime::fromProject($this->dir)?->version, '4.33.1');
    }

    public function expandsUserDirInVendorDir(): void
    {
        \mkdir($this->dir . '/project');
        \file_put_contents($this->dir . '/project/composer.json', '{"config": {"vendor-dir": "~/deps"}}');
        $this->installed('deps', ['google/protobuf' => ['pretty_version' => '4.33.1']]);
        $_SERVER['HOME'] = $this->dir;

        Assert::same(ProtobufRuntime::fromProject($this->dir . '/project')?->version, '4.33.1');
    }

    public function findsProjectUpFromWorkingDir(): void
    {
        \file_put_contents($this->dir . '/composer.json', '{}');
        \mkdir($this->dir . '/proto/v1', 0777, true);

        Assert::same(ProtobufRuntime::projectDir($this->dir . '/proto/v1'), $this->dir);
        Assert::same(ProtobufRuntime::projectDir($this->dir), $this->dir);
    }

    public function findsProjectByComposerVariable(): void
    {
        \file_put_contents($this->dir . '/composer.json', '{}');
        \mkdir($this->dir . '/app/proto', 0777, true);
        \file_put_contents($this->dir . '/app/app.json', '{}');
        $_SERVER['COMPOSER'] = 'app.json';

        Assert::same(ProtobufRuntime::projectDir($this->dir . '/app/proto'), $this->dir . '/app');
    }

    public function stopsLookingForProjectAtUserHome(): void
    {
        \file_put_contents($this->dir . '/composer.json', '{}');
        \mkdir($this->dir . '/home/proto', 0777, true);
        $_SERVER['HOME'] = $this->dir . '/home';

        Assert::same(ProtobufRuntime::projectDir($this->dir . '/home/proto'), $this->dir . '/home/proto');
    }

    #[DataSet([['roadrunner/worker' => ['pretty_version' => '3.8.0']]], 'other packages')]
    #[DataSet([['google/protobuf' => ['replaced' => ['*']]]], 'replaced package')]
    public function findsNothingInProjectWithoutPackage(array $versions): void
    {
        $this->installed('vendor', $versions);

        Assert::null(ProtobufRuntime::fromProject($this->dir));
    }

    public function findsNothingInProjectWithoutVendor(): void
    {
        Assert::null(ProtobufRuntime::fromProject($this->dir));
    }

    public function readsVersionFromRunningInstallation(): void
    {
        InstalledVersions::reload(['versions' => ['google/protobuf' => ['pretty_version' => 'v4.33.1']]]);

        $runtime = ProtobufRuntime::fromInstalledVersions();

        Assert::same($runtime?->name, 'google/protobuf');
        Assert::same($runtime?->version, 'v4.33.1');
    }

    public function findsNothingInRunningInstallationWithoutPackage(): void
    {
        InstalledVersions::reload(['versions' => ['roadrunner/worker' => ['pretty_version' => 'v3.8.0']]]);

        Assert::null(ProtobufRuntime::fromInstalledVersions());
    }

    public function prefersProjectOverRunningInstallation(): void
    {
        $this->installed('vendor', ['google/protobuf' => ['pretty_version' => '5.36.2']]);
        InstalledVersions::reload(['versions' => ['google/protobuf' => ['pretty_version' => '4.26.0']]]);

        Assert::same(ProtobufRuntime::detect($this->dir)?->version, \phpversion('protobuf') ?: '5.36.2');
    }

    public function fallsBackToRunningInstallation(): void
    {
        InstalledVersions::reload(['versions' => ['google/protobuf' => ['pretty_version' => '4.26.0']]]);

        Assert::same(ProtobufRuntime::detect($this->dir)?->version, \phpversion('protobuf') ?: '4.26.0');
    }

    /**
     * The package next to this tool is not the project's runtime.
     */
    public function ignoresRunningInstallationWhenProjectLacksPackage(): void
    {
        \extension_loaded('protobuf') and throw new SkipTest('ext-protobuf is loaded');
        $this->installed('vendor', ['roadrunner/worker' => ['pretty_version' => '3.8.0']]);
        InstalledVersions::reload(['versions' => ['google/protobuf' => ['pretty_version' => '4.26.0']]]);

        Assert::null(ProtobufRuntime::detect($this->dir));
    }

    /**
     * A loaded extension defines the protobuf classes itself, so the package is never used.
     */
    public function prefersExtensionOverPackage(): void
    {
        $this->installed('vendor', ['google/protobuf' => ['pretty_version' => '5.36.2']]);

        $runtime = ProtobufRuntime::detect($this->dir);

        Assert::same($runtime?->name, \extension_loaded('protobuf') ? 'ext-protobuf' : 'google/protobuf');
    }

    public function readsExtensionVersion(): void
    {
        $version = \phpversion('protobuf');

        $runtime = ProtobufRuntime::fromExtension();

        Assert::same($runtime?->version, $version === false ? null : $version);
    }

    #[BeforeTest]
    protected function isolate(): void
    {
        $this->dir = TempDirectory::create();
        $this->server = $_SERVER;
        unset($_SERVER['COMPOSER'], $_ENV['COMPOSER']);
        $ref = new \ReflectionClass(InstalledVersions::class);
        $this->canGetVendors = $ref->getStaticPropertyValue('canGetVendors');
        $ref->setStaticPropertyValue('canGetVendors', false);
        InstalledVersions::reload(['versions' => []]);
    }

    #[AfterTest]
    protected function restore(): void
    {
        $_SERVER = $this->server;
        TempDirectory::remove($this->dir);
        InstalledVersions::reload(null);
        (new \ReflectionClass(InstalledVersions::class))->setStaticPropertyValue('canGetVendors', $this->canGetVendors);
    }

    private function installed(string $vendor, array $versions): void
    {
        \mkdir($this->dir . '/' . $vendor . '/composer', 0777, true);
        \file_put_contents(
            $this->dir . '/' . $vendor . '/composer/installed.php',
            '<?php return ' . \var_export(['root' => [], 'versions' => $versions], true) . ';',
        );
    }
}
