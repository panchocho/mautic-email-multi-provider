<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class PriorityBoostStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'priority_boost';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        if ($request->priority < 8) {
            return 0.0;
        }

        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return 0.0;
        }

        return in_array('transactional', $profile->tags, true) ? 5.0 : -1.0;
    }
}

