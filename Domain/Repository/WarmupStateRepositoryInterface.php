<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\WarmupState;

interface WarmupStateRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProvider(Provider $provider): ?WarmupState;
}

