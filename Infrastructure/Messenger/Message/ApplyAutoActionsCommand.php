<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message;

final class ApplyAutoActionsCommand
{
    /**
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public readonly string $providerName,
        public readonly array $metrics = []
    ) {
    }
}

