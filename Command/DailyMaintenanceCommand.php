<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Command;

use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\QueueMaintenanceService;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SchemaInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smart-mailer:maintenance:daily',
    description: 'Run SmartMailerRouter maintenance sweep and retry processing in one pass.'
)]
final class DailyMaintenanceCommand extends Command
{
    public function __construct(
        private readonly SchemaInitializer $schemaInitializer,
        private readonly QueueMaintenanceService $maintenanceService,
        private readonly RetryQueueService $retryQueueService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('retry-limit', null, InputOption::VALUE_REQUIRED, 'Maximum due retries to process in the daily pass.', 100)
            ->addOption('skip-sweep', null, InputOption::VALUE_NONE, 'Skip the maintenance sweep phase.')
            ->addOption('skip-retry', null, InputOption::VALUE_NONE, 'Skip the retry processing phase.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schemaInitializer->ensureInitialized();

        $io = new SymfonyStyle($input, $output);
        $skipSweep = (bool) $input->getOption('skip-sweep');
        $skipRetry = (bool) $input->getOption('skip-retry');
        $retryLimit = max(1, (int) $input->getOption('retry-limit'));

        $sweepResult = [
            'delivery_logs_deleted' => 0,
            'delivery_archive_deleted' => 0,
            'health_history_deleted' => 0,
            'retry_rows_promoted' => 0,
            'retry_rows_expired' => 0,
            'retry_rows_deleted' => 0,
        ];
        $retryResult = [
            'processed' => 0,
            'requeued' => 0,
            'expired' => 0,
            'invalid' => 0,
        ];

        if (!$skipSweep) {
            $sweepResult = $this->maintenanceService->sweep();
        }

        if (!$skipRetry) {
            $retryResult = $this->retryQueueService->processDue($retryLimit);
        }

        $io->success(sprintf(
            'Daily maintenance completed. sweep=%s(delivery_logs_deleted=%d delivery_archive_deleted=%d health_history_deleted=%d retry_rows_promoted=%d retry_rows_expired=%d retry_rows_deleted=%d) retry=%s(limit=%d processed=%d requeued=%d expired=%d invalid=%d)',
            $skipSweep ? 'skipped' : 'run',
            $sweepResult['delivery_logs_deleted'],
            $sweepResult['delivery_archive_deleted'],
            $sweepResult['health_history_deleted'],
            $sweepResult['retry_rows_promoted'],
            $sweepResult['retry_rows_expired'],
            $sweepResult['retry_rows_deleted'],
            $skipRetry ? 'skipped' : 'run',
            $retryLimit,
            $retryResult['processed'],
            $retryResult['requeued'],
            $retryResult['expired'],
            $retryResult['invalid']
        ));

        return Command::SUCCESS;
    }
}
