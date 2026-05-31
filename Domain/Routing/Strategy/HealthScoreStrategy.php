<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class HealthScoreStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'health_score';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $snapshot = $context->health[$provider] ?? null;
        if ($snapshot === null) {
            return 0.0;
        }

        return ($snapshot->score - 50.0) / 10.0;
    }
}

