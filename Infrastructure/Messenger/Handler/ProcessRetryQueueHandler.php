<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Handler;

use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\ProcessRetryQueueCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessRetryQueueHandler
{
    public function __construct(
        private readonly RetryQueueService $retryQueueService
    ) {
    }

    /**
     * @return array{processed:int,requeued:int,expired:int,invalid:int}
     */
    public function __invoke(ProcessRetryQueueCommand $command): array
    {
        return $this->retryQueueService->processDue($command->limit);
    }
}
