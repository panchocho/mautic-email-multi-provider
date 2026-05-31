<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class PercentageSplitModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::PERCENTAGE_SPLIT;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        /** @var array<string, int> $split */
        $split = (array) ($request->metadata['percentage_split'] ?? []);
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = (float) ($split[$name] ?? $profile->weight);
        }

        return $this->sortByScore($scores);
    }
}

