<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class PriorityBasedModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::PRIORITY_BASED;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = ($profile->priority * 1000) + $profile->healthScore;
        }

        return $this->sortByScore($scores);
    }
}

