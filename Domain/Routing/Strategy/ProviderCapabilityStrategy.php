<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class ProviderCapabilityStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'provider_capability';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return -100.0;
        }

        return in_array($request->messageType, $profile->supportedMessageTypes, true) ? 6.0 : -100.0;
    }
}

