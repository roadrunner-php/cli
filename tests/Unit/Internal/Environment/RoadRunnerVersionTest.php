<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Environment;

use Composer\InstalledVersions;
use Spiral\RoadRunner\Console\Internal\Environment\RoadRunnerVersion;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class RoadRunnerVersionTest
{
    private ?bool $canGetVendors = null;

    public static function provideVersions(): iterable
    {
        yield 'new name' => [['roadrunner/roadrunner' => ['pretty_version' => 'v3.0.0']], '3.*'];
        yield 'old name' => [['spiral/roadrunner' => ['pretty_version' => 'v2025.1.15']], '2025.*'];
        yield 'both names' => [
            [
                'spiral/roadrunner' => ['pretty_version' => 'v2025.1.15'],
                'roadrunner/roadrunner' => ['pretty_version' => 'v3.0.0'],
            ],
            '3.*',
        ];
        yield 'worker only' => [['roadrunner/worker' => ['pretty_version' => 'v3.8.0']], '3.*'];
        yield 'dev branch' => [['roadrunner/roadrunner' => ['pretty_version' => 'dev-master']], '3.*'];
    }

    #[DataProvider('provideVersions')]
    public function constraint(array $versions, string $expected): void
    {
        InstalledVersions::reload(['versions' => $versions]);

        Assert::same(RoadRunnerVersion::constraint(), $expected);
    }

    #[BeforeTest]
    protected function isolate(): void
    {
        $ref = new \ReflectionClass(InstalledVersions::class);
        $this->canGetVendors = $ref->getStaticPropertyValue('canGetVendors');
        $ref->setStaticPropertyValue('canGetVendors', false);
    }

    #[AfterTest]
    protected function restore(): void
    {
        // `null` makes the next lookup read installed.php from the registered vendor dirs again.
        InstalledVersions::reload(null);
        (new \ReflectionClass(InstalledVersions::class))->setStaticPropertyValue('canGetVendors', $this->canGetVendors);
    }
}
