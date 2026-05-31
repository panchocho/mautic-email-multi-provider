<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class GeoRoutingModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::GEO_ROUTING;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $geo = strtolower((string) ($request->metadata['geo'] ?? $request->region));
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = in_array($geo, $profile->supportedRegions, true) ? 1000 : 100;
            $scores[$name] += $profile->healthScore;
        }

        return $this->sortByScore($scores);
    }
}

