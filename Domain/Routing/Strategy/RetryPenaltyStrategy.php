<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class RetryPenaltyStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'retry_penalty';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $retryRate = $this->providerFloat($context, $provider, 'retry_rate', 0.0);
        $retryRate = max(0.0, min(1.0, $retryRate));

        return -1.0 * $retryRate * 10.0;
    }
}

