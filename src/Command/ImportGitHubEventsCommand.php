<?php

declare(strict_types=1);

namespace App\Command;

use App\GitHubArchive\EventsDownloader;
use App\GitHubArchive\EventsExtractor;
use App\GitHubArchive\EventsImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Symfony\Component\String\u;

/**
 * This command must import GitHub events.
 * You can add the parameters and code you want in this command to meet the need.
 */
#[AsCommand('app:import-github-events', 'Import GH events')]
class ImportGitHubEventsCommand extends Command
{
    public function __construct(
        private readonly EventsDownloader $downloader,
        private readonly EventsExtractor $extractor,
        private readonly EventsImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'date',
            InputArgument::OPTIONAL,
            'Date (<comment>YYYY-MM-DD</comment> format @ UTC timezone) to download GH events from',
            (new \DateTime('yesterday'))->format('Y-m-d'),
        );

        $this->addOption(
            'batch-size',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of events to save in a single batch',
            500,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = (string) $input->getArgument('date');
        $batchSize = (int) $input->getOption('batch-size');

        if ($batchSize < 1) {
            $io->error(sprintf('--batch-size must be greater than 0, %d given.', $batchSize));

            return Command::FAILURE;
        }

        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $date)) {
            $io->error(sprintf('Invalid date format. Must be YYYY-MM-DD, but "%s" given.', $date));

            return Command::FAILURE;
        }

        $date = new \DateTimeImmutable(sprintf('%sT00:00:00Z', $date));

        if ($date > new \DateTimeImmutable()) {
            $io->error(sprintf('The given date "%s" is invalid, it must be in the past.', $date->format('Y-m-d')));
        }

        $io->writeln(sprintf('Fetching GitHub events from <info>%s</info>...', $date->format('Y-m-d')));

        $paths = $this->downloader->download($date);
        $pathCount = count($paths);

        $io->writeln(sprintf('<info>%d</info> archives to process', $pathCount));

        foreach ($paths as $i => $path) {
            $io->section(sprintf('(%s/%d) %s', u((string) ($i + 1))->padStart(2), $pathCount, $path));

            $dest = $this->extractor->extract($path);

            $progress = $io->createProgressBar();
            $progress->setFormat('debug');

            $onStart = static fn (int $total) => $progress->setMaxSteps($total);
            $onItem = static fn () => $progress->advance();
            $onFinish = static function () use ($progress, $io) {
                $progress->finish();
                $io->newLine();
            };

            $this->importer->import($dest, $onStart, $onItem, $onFinish, $batchSize);

            $progress->finish();
            $io->newLine();
        }

        return Command::SUCCESS;
    }
}
