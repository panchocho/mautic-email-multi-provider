<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RetryQueueEntry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\RetryQueueEntryRepositoryInterface;

final class RetryQueueEntryRepository extends AbstractDoctrineRepository implements RetryQueueEntryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RetryQueueEntry::class);
    }

    public function findDue(DateTimeImmutable $at, int $limit = 1000): array
    {
        return $this->createQueryBuilder('rq')
            ->andWhere('rq.status = :status')
            ->andWhere('rq.nextAttemptAt <= :at')
            ->setParameter('status', 'pending')
            ->setParameter('at', $at)
            ->orderBy('rq.nextAttemptAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

