<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderHealthHistory;

interface ProviderHealthHistoryRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<ProviderHealthHistory> */
    public function findRecentForProvider(Provider $provider, int $limit = 20): array;

    public function findLatestForProvider(Provider $provider): ?ProviderHealthHistory;
}

