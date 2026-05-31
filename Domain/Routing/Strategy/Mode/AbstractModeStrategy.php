<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;

abstract class AbstractModeStrategy
{
    /**
     * @return array<string, ProviderProfile>
     */
    protected function providers(RoutingContext $context): array
    {
        return $context->providers;
    }

    /**
     * @param array<string, float|int> $scores
     * @return list<string>
     */
    protected function sortByScore(array $scores): array
    {
        arsort($scores, SORT_NUMERIC);

        return array_keys($scores);
    }
}

