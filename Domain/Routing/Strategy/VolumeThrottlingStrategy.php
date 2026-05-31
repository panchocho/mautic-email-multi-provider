<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class VolumeThrottlingStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'volume_throttling';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null || $profile->getMaxQps() <= 0) {
            return 0.0;
        }

        $recentVolume = $this->providerFloat($context, $provider, 'recent_qps', 0.0);
        $usageRatio = $recentVolume / $profile->getMaxQps();

        if ($usageRatio <= 0.8) {
            return 1.0;
        }

        if ($usageRatio <= 1.0) {
            return -2.0;
        }

        return -8.0 * min(2.0, $usageRatio);
    }
}
