<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class WeightedRoundRobinModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::WEIGHTED_ROUND_ROBIN;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $weighted = [];
        foreach ($this->providers($context) as $name => $profile) {
            $weighted[$name] = ($profile->weight * 1000) - abs(crc32($request->requestId . ':' . $name)) % 1000;
        }

        return $this->sortByScore($weighted);
    }
}

