<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message;

final class RecomputeHealthCommand
{
    public function __construct(public readonly string $providerName)
    {
    }
}

