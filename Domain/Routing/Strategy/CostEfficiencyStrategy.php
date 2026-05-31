<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class CostEfficiencyStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'cost_efficiency';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return 0.0;
        }

        $maxCost = (float) ($request->metadata['max_cost_micros'] ?? 15000);
        $cost = $profile->getBaseCostMicros();

        if ($cost <= 0) {
            return 3.0;
        }

        $ratio = min(2.0, $maxCost / $cost);

        return ($ratio - 1.0) * 4.0;
    }
}
