<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RoutingRule;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\RoutingRuleRepositoryInterface;

final class RoutingRuleRepository extends AbstractDoctrineRepository implements RoutingRuleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutingRule::class);
    }

    /** @return list<RoutingRule> */
    public function findEnabledByProfile(RoutingProfile $profile): array
    {
        return $this->findBy(
            ['profile' => $profile, 'enabled' => true],
            ['priority' => 'DESC', 'weight' => 'DESC']
        );
    }
}
