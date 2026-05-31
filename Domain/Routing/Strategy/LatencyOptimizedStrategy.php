<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class LatencyOptimizedStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'latency_optimized';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return 0.0;
        }

        $observedLatency = $this->providerFloat($context, $provider, 'latency_ms', (float) $profile->getBaseLatencyMs());
        $target = (float) ($request->metadata['target_latency_ms'] ?? 400);
        $gap = max(-600.0, min(600.0, $target - $observedLatency));

        return $gap / 100.0;
    }
}
