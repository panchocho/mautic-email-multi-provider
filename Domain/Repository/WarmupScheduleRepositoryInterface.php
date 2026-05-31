<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\WarmupSchedule;

interface WarmupScheduleRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<WarmupSchedule> */
    public function findActiveForProvider(Provider $provider): array;
}

