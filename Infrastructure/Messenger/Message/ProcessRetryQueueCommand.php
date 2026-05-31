<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message;

final class ProcessRetryQueueCommand
{
    public function __construct(public readonly int $limit = 100)
    {
    }
}
