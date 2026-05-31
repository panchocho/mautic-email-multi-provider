<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class RegionAffinityStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'region_affinity';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return -10.0;
        }

        return in_array($request->region, $profile->supportedRegions, true) ? 8.0 : -50.0;
    }
}

