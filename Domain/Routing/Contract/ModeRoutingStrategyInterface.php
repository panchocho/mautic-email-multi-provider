<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

interface ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode;

    /**
     * @return list<string> Ordered providers (best first)
     */
    public function rank(RoutingRequest $request, RoutingContext $context): array;
}

