<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Handler;

use MauticPlugin\SmartMailerRouterBundle\Application\Health\HealthScoreService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RecomputeHealthCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RecomputeHealthHandler
{
    public function __construct(private readonly HealthScoreService $healthScoreService)
    {
    }

    public function __invoke(RecomputeHealthCommand $command): void
    {
        $this->healthScoreService->recomputeForProvider($command->providerName);
    }
}

