<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\WarmupState;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\WarmupStateRepositoryInterface;

final class WarmupStateRepository extends AbstractDoctrineRepository implements WarmupStateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarmupState::class);
    }

    public function findByProvider(Provider $provider): ?WarmupState
    {
        return $this->findOneBy(['provider' => $provider]);
    }
}

