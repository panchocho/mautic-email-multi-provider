<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\RoutingProfileRepositoryInterface;

final class RoutingProfileRepository extends AbstractDoctrineRepository implements RoutingProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutingProfile::class);
    }

    public function findById(int $id): ?RoutingProfile
    {
        return $this->find($id);
    }

    public function findOneByName(string $name): ?RoutingProfile
    {
        return $this->findOneBy(['name' => $name]);
    }

    /** @return list<RoutingProfile> */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }
}

