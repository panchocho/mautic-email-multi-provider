<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Command;

use MauticPlugin\SmartMailerRouterBundle\Application\Deliverability\ResendBounceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smart-mailer:resend:sync-bounces',
    description: 'Query known Resend message IDs and add bounced, suppressed, or complained contacts to Mautic DNC.'
)]
final class ResendBounceSyncCommand extends Command
{
    public function __construct(
        private readonly ResendBounceService $bounceService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum known messages to query', '500');
        $this->addOption('email', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Import a known Resend-bounced recipient');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emails = array_values(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            (array) $input->getOption('email')
        )));
        if ($emails !== []) {
            $result = $this->bounceService->processWebhook([
                'type' => 'email.bounced',
                'data' => [
                    'to' => $emails,
                    'reason' => 'Imported from Resend bounce history.',
                ],
            ]);
            (new SymfonyStyle($input, $output))->success(sprintf(
                'Imported Resend bounce list: processed=%d matched=%d skipped=%d',
                $result['processed'], $result['matched'], $result['skipped']
            ));
            return Command::SUCCESS;
        }

        $result = $this->bounceService->syncKnownDeliveries((int) $input->getOption('limit'));
        (new SymfonyStyle($input, $output))->success(sprintf(
            'Resend sync: checked=%d bounced=%d matched=%d errors=%d',
            $result['checked'], $result['bounced'], $result['matched'], $result['errors']
        ));

        return $result['errors'] > 0 && $result['checked'] === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
