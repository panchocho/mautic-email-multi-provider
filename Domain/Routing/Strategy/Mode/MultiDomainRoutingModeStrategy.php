<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class MultiDomainRoutingModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::MULTI_DOMAIN_ROUTING;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        /** @var list<string> $preferredDomains */
        $preferredDomains = array_map('strtolower', (array) ($request->metadata['allowed_domains'] ?? []));
        $scores = [];

        foreach ($this->providers($context) as $name => $profile) {
            $matches = 0;
            foreach ($preferredDomains as $domain) {
                if ($profile->supportsDomain($domain)) {
                    ++$matches;
                }
            }
            $scores[$name] = ($matches * 200) + $profile->healthScore + $profile->priority;
        }

        return $this->sortByScore($scores);
    }
}

