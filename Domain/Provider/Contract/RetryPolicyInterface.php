<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract;

interface RetryPolicyInterface
{
    public function shouldRetry(int $attempt, string $reason): bool;

    public function nextDelaySeconds(int $attempt, string $reason): int;
}

