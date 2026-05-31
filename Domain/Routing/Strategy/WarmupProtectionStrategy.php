<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class WarmupProtectionStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'warmup_protection';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null || !$profile->isWarmupEnabled()) {
            return 0.0;
        }

        if ($request->priority >= 8) {
            return -8.0;
        }

        return 2.0;
    }
}
