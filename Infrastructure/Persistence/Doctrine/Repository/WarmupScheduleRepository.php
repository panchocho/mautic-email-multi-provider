<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\WarmupSchedule;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\WarmupScheduleRepositoryInterface;

final class WarmupScheduleRepository extends AbstractDoctrineRepository implements WarmupScheduleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarmupSchedule::class);
    }

    /** @return list<WarmupSchedule> */
    public function findActiveForProvider(Provider $provider): array
    {
        return $this->findBy(
            ['provider' => $provider, 'active' => true],
            ['dayOfWeek' => 'ASC', 'hourUtc' => 'ASC']
        );
    }
}

