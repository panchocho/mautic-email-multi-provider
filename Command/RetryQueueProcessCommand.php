<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Command;

use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SchemaInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smart-mailer:retry:process',
    description: 'Process due SmartMailerRouter retry queue entries.'
)]
final class RetryQueueProcessCommand extends Command
{
    public function __construct(
        private readonly SchemaInitializer $schemaInitializer,
        private readonly RetryQueueService $retryQueueService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schemaInitializer->ensureInitialized();

        $io = new SymfonyStyle($input, $output);
        $result = $this->retryQueueService->processDue(100);

        $io->success(sprintf(
            'Retry queue processed. processed=%d requeued=%d expired=%d invalid=%d',
            $result['processed'],
            $result['requeued'],
            $result['expired'],
            $result['invalid']
        ));

        return Command::SUCCESS;
    }
}
