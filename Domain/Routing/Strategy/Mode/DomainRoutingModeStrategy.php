<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class DomainRoutingModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::DOMAIN_ROUTING;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $domain = strtolower(ltrim((string) (strrchr($request->recipient, '@') ?: ''), '@'));
        $scores = [];
        foreach ($this->providers($context) as $name => $profile) {
            $scores[$name] = $profile->supportsDomain($domain) ? 1000 + $profile->priority : -1000;
        }

        return $this->sortByScore($scores);
    }
}

