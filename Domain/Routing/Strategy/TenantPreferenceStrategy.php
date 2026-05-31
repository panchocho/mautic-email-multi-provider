<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class TenantPreferenceStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'tenant_preference';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $weights = $request->metadata['tenant_provider_weights'] ?? [];
        if (!is_array($weights)) {
            return 0.0;
        }

        $weight = $weights[$provider] ?? 0.0;

        return is_numeric($weight) ? (float) $weight : 0.0;
    }
}

