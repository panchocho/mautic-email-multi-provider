<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingRule;

interface RoutingRuleRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<RoutingRule> */
    public function findEnabledByProfile(RoutingProfile $profile): array;
}

