<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Handler;

use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessor;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\RetryPolicyInterface;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RouteEmailHandler
{
    public function __construct(
        private readonly RouteEmailProcessor $processor,
        private readonly RetryQueueService $retryQueueService,
        private readonly RetryPolicyInterface $retryPolicy,
    ) {
    }

    public function __invoke(RouteEmailCommand $command): void
    {
        $execution = $this->processor->process($command);

        $retryScheduled = false;
        if (!$execution->result->accepted) {
            $retryScheduled = $this->retryPolicy->shouldRetry(1, (string) ($execution->result->reason ?? 'temporary_failure'))
                && $this->retryQueueService->scheduleFailure(
                    $command,
                    $execution->primaryProvider,
                    $execution->providerCode,
                    $execution->result
                );
        }
    }
}
