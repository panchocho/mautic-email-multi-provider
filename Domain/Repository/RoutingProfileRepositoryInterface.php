<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingProfile;

interface RoutingProfileRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?RoutingProfile;

    public function findOneByName(string $name): ?RoutingProfile;

    /** @return list<RoutingProfile> */
    public function findEnabled(): array;
}

