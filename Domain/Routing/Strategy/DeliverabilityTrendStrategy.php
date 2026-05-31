<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class DeliverabilityTrendStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'deliverability_trend';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $trend = $this->providerFloat($context, $provider, 'deliverability_trend', 0.0);
        $trend = max(-1.0, min(1.0, $trend));

        return $trend * 5.0;
    }
}

