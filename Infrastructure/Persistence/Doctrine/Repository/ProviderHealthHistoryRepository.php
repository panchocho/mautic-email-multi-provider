<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderHealthHistory;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\ProviderHealthHistoryRepositoryInterface;

final class ProviderHealthHistoryRepository extends AbstractDoctrineRepository implements ProviderHealthHistoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderHealthHistory::class);
    }

    /** @return list<ProviderHealthHistory> */
    public function findRecentForProvider(Provider $provider, int $limit = 20): array
    {
        return $this->findBy(
            ['provider' => $provider],
            ['checkedAt' => 'DESC'],
            $limit
        );
    }

    public function findLatestForProvider(Provider $provider): ?ProviderHealthHistory
    {
        return $this->findOneBy(['provider' => $provider], ['checkedAt' => 'DESC']);
    }
}

