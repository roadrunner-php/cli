<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Internal\Configuration;

use Spiral\RoadRunner\Console\Internal\Configuration\Generator;
use Spiral\RoadRunner\Console\Internal\Configuration\Plugins;
use Symfony\Component\Yaml\Yaml;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
final class GeneratorTest
{
    public function generatesDefaultConfiguration(): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins([])));

        Assert::same(\array_keys($config), ['version', 'rpc', 'server', 'http', 'jobs', 'kv', 'metrics']);
        Assert::same($config['version'], '3');
    }

    #[DataSet(['http'])]
    #[DataSet(['jobs'])]
    #[DataSet(['grpc'])]
    #[DataSet(['temporal'])]
    #[DataSet(['centrifuge'])]
    public function addsServerForWorkerPlugins(string $plugin): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins([$plugin])));

        Assert::same(\array_keys($config), ['version', 'rpc', $plugin, 'server']);
    }

    #[DataSet(['amqp'])]
    #[DataSet(['beanstalk'])]
    #[DataSet(['google_pub_sub'])]
    #[DataSet(['kafka'])]
    #[DataSet(['nats'])]
    #[DataSet(['nsq'])]
    #[DataSet(['sqs'])]
    public function addsJobsForBrokerPlugins(string $plugin): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins([$plugin])));

        Assert::same(\array_keys($config), ['version', 'rpc', $plugin, 'jobs', 'server']);
    }

    public function addsGrpcForProtoreg(): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins(['protoreg'])));

        Assert::same(\array_keys($config), ['version', 'rpc', 'protoreg', 'grpc', 'server']);
    }

    public function rendersKvMemoryConfigAsMap(): void
    {
        $yaml = (new Generator())->generate(Plugins::fromPlugins(['kv']));

        Assert::string($yaml)->matchesRegex('/^\s+config: \{\s*\}$/m');
    }

    public function rendersEachSectionOnce(): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins(['http', 'jobs', 'server'])));

        Assert::array(\array_keys($config))->sameElementsAs(['version', 'rpc', 'http', 'jobs', 'server']);
    }

    public function rendersNestedSectionContent(): void
    {
        $config = Yaml::parse((new Generator())->generate(Plugins::fromPlugins(['http'])));

        Assert::same($config['http']['address'], '0.0.0.0:8080');
        Assert::same($config['rpc'], ['listen' => 'tcp://127.0.0.1:6001']);
    }
}
