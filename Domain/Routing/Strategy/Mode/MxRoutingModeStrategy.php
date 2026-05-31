<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class MxRoutingModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::MX_ROUTING;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $mxClass = strtolower((string) ($request->metadata['mx_class'] ?? 'default'));
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $bonus = in_array($mxClass, $profile->tags, true) ? 150 : 0;
            $scores[$name] = $profile->healthScore + $bonus;
        }

        return $this->sortByScore($scores);
    }
}

