<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

interface ProviderGuardInterface
{
    public function isEligible(ProviderProfile $profile, RoutingRequest $request): bool;
}

