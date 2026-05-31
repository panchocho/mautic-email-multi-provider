<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class EngagementBasedModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::ENGAGEMENT_BASED;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $segment = (string) ($request->metadata['engagement_segment'] ?? 'cold');
        $premiumBias = $segment === 'warm' || $segment === 'hot';

        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $tagBonus = $premiumBias && in_array('premium', $profile->tags, true) ? 200 : 0;
            $scores[$name] = $profile->healthScore + $tagBonus + $profile->priority;
        }

        return $this->sortByScore($scores);
    }
}

