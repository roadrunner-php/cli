<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit;

use Spiral\RoadRunner\Console\DownloadProtocBinaryCommand;
use Testo\Assert;
use Testo\Test;

/**
 * The command is final and always queries GitHub, so only its definition is covered here.
 */
#[Test]
final class DownloadProtocBinaryCommandTest
{
    public function describesItself(): void
    {
        $command = new DownloadProtocBinaryCommand();

        Assert::same($command->getName(), 'download-protoc-binary');
        Assert::same($command->getDescription(), 'Install or update protoc-gen-php-grpc binary');
    }

    public function definesEnvironmentOptions(): void
    {
        $definition = (new DownloadProtocBinaryCommand())->getDefinition();

        Assert::array(\array_keys($definition->getOptions()))
            ->sameElementsAs(['os', 'arch', 'filter', 'location', 'stability']);
        Assert::same($definition->getOption('stability')->getDefault(), 'stable');
    }
}
