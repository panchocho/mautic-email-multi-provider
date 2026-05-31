<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class TimeWindowStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'time_window';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $preferredHours = $this->providerList($context, $provider, 'preferred_hours');
        if ($preferredHours === []) {
            return 0.0;
        }

        $hour = (int) $request->occurredAt->format('G');
        $hourString = (string) $hour;

        return in_array($hourString, $preferredHours, true) ? 2.5 : -1.0;
    }
}

