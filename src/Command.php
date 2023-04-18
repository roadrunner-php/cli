<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console;

use Spiral\RoadRunner\Console\Environment\Environment;
use Spiral\RoadRunner\Console\Repository\GitHub\GitHubRepository;
use Spiral\RoadRunner\Console\Repository\RepositoriesCollection;
use Spiral\RoadRunner\Console\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

abstract class Command extends BaseCommand
{
    private const ENV_GITHUB_TOKEN = 'GITHUB_TOKEN';

    protected function getRepository(): RepositoryInterface
    {
        $token = Environment::get(self::ENV_GITHUB_TOKEN);

        $client = HttpClient::create([
            'headers' => \array_filter([
                'authorization' => $token ? 'token ' . $token : null
            ])
        ]);

        return new RepositoriesCollection([
            GitHubRepository::create('roadrunner-server', 'roadrunner', $client),
        ]);
    }

    protected function io(InputInterface $input, OutputInterface $output): StyleInterface
    {
        return new SymfonyStyle($input, $output);
    }

    protected function confirm(InputInterface $input, OutputInterface $out, string $message): bool
    {
        $question = new ConfirmationQuestion($message);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return (bool)$helper->ask($input, $out, $question);
    }
}
