<?php

namespace App\Command;

use App\Repository\AnalyticsHitRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analytics:cleanup',
    description: 'Löscht vorhandene Bilder und Media-Aufrufe aus den Analytics-Logs',
)]
class AnalyticsCleanupCommand extends Command
{
    public function __construct(
        private AnalyticsHitRepository $analyticsHitRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$io->confirm('Möchten Sie alle vorhandenen Tracking-Einträge für Bilder und Media-Dateien löschen?', false)) {
            return Command::SUCCESS;
        }

        $count = $this->analyticsHitRepository->deleteImages();

        $io->success(sprintf('%d Einträge wurden erfolgreich gelöscht.', $count));

        return Command::SUCCESS;
    }
}
