<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\DeliveryLog;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;

interface DeliveryLogRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<DeliveryLog> */
    public function findRecentByProvider(Provider $provider, int $limit = 100): array;

    /** @return list<DeliveryLog> */
    public function findRecentFailuresByProvider(Provider $provider, int $limit = 50): array;
}

