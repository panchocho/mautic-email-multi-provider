<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class TagBasedModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::TAG_BASED;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        /** @var list<string> $messageTags */
        $messageTags = array_map('strval', (array) ($request->metadata['tags'] ?? []));
        $scores = [];

        foreach ($this->providers($context) as $name => $profile) {
            $overlap = count(array_intersect($messageTags, $profile->tags));
            $scores[$name] = ($overlap * 100) + $profile->priority;
        }

        return $this->sortByScore($scores);
    }
}

