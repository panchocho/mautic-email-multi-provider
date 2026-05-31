<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Command;

use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\QueueMaintenanceService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SchemaInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smart-mailer:maintenance:sweep',
    description: 'Sweep SmartMailerRouter operational tables and normalize retry queue state.'
)]
final class QueueMaintenanceSweepCommand extends Command
{
    public function __construct(
        private readonly SchemaInitializer $schemaInitializer,
        private readonly QueueMaintenanceService $maintenanceService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schemaInitializer->ensureInitialized();

        $io = new SymfonyStyle($input, $output);
        $result = $this->maintenanceService->sweep();

        $io->success(sprintf(
            'Sweep completed. delivery_logs_deleted=%d delivery_archive_deleted=%d health_history_deleted=%d retry_rows_promoted=%d retry_rows_expired=%d retry_rows_deleted=%d',
            $result['delivery_logs_deleted'],
            $result['delivery_archive_deleted'],
            $result['health_history_deleted'],
            $result['retry_rows_promoted'],
            $result['retry_rows_expired'],
            $result['retry_rows_deleted']
        ));

        return Command::SUCCESS;
    }
}
