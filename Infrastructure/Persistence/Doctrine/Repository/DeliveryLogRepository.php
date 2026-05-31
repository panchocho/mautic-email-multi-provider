<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\DeliveryLog;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\DeliveryLogRepositoryInterface;

final class DeliveryLogRepository extends AbstractDoctrineRepository implements DeliveryLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryLog::class);
    }

    /** @return list<DeliveryLog> */
    public function findRecentByProvider(Provider $provider, int $limit = 100): array
    {
        return $this->findBy(
            ['provider' => $provider],
            ['attemptedAt' => 'DESC'],
            $limit
        );
    }

    /** @return list<DeliveryLog> */
    public function findRecentFailuresByProvider(Provider $provider, int $limit = 50): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.provider = :provider')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('provider', $provider)
            ->setParameter('statuses', ['failed', 'bounced', 'deferred'])
            ->orderBy('d.attemptedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

