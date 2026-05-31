<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingDecision;

interface RoutingDecisionCacheInterface
{
    public function get(string $cacheKey): ?RoutingDecision;

    public function put(string $cacheKey, RoutingDecision $decision, int $ttlSeconds): void;
}

