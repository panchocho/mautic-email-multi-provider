<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class RoutingPlan
{
    /**
     * @param list<string> $providerNames
     * @param list<int>    $matchedRuleIds
     * @param array<string, list<string>> $providerCodesByName
     */
    public function __construct(
        public readonly RoutingMode $mode,
        public readonly array $providerNames,
        public readonly ?int $profileId = null,
        public readonly ?string $profileName = null,
        public readonly array $matchedRuleIds = [],
        public readonly array $providerCodesByName = []
    ) {
    }
}
