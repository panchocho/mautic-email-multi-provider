<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class CostOptimizedModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::COST_OPTIMIZED;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = max(1.0, 100.0 - ($profile->costPerEmail * 100000)) + ($profile->healthScore * 0.3);
        }

        return $this->sortByScore($scores);
    }
}

