<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class FailoverModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::FAILOVER;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = ($profile->healthScore * 10) + $profile->priority - ($profile->costPerEmail * 100000);
        }

        return $this->sortByScore($scores);
    }
}

