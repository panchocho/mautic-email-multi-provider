<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Handler;

use MauticPlugin\SmartMailerRouterBundle\Application\Health\AutoActionsService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\ApplyAutoActionsCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ApplyAutoActionsHandler
{
    public function __construct(private readonly AutoActionsService $autoActionsService)
    {
    }

    public function __invoke(ApplyAutoActionsCommand $command): void
    {
        $this->autoActionsService->apply($command->providerName);
    }
}

