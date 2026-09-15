<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Command;

use MauticPlugin\SmartMailerRouterBundle\Application\Deliverability\ResendBounceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smart-mailer:resend:register-webhook',
    description: 'Register the Mautic endpoint for Resend bounce, suppression, and complaint events.'
)]
final class ResendWebhookRegisterCommand extends Command
{
    public function __construct(private readonly ResendBounceService $bounceService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('endpoint', InputArgument::REQUIRED, 'Public HTTPS endpoint that Resend can call');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $endpoint = trim((string) $input->getArgument('endpoint'));
        try {
            $result = $this->bounceService->registerWebhook($endpoint);
        } catch (\Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return Command::FAILURE;
        }
        (new SymfonyStyle($input, $output))->success('Resend webhook registered: '.$result['webhook_id']);
        return Command::SUCCESS;
    }
}
