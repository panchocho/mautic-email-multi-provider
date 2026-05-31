<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderDomainBinding;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\ProviderDomainBindingRepositoryInterface;

final class ProviderDomainBindingRepository extends AbstractDoctrineRepository implements ProviderDomainBindingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderDomainBinding::class);
    }

    /** @return list<ProviderDomainBinding> */
    public function findActiveByDomain(string $domain): array
    {
        return $this->findBy(
            ['domain' => mb_strtolower($domain), 'active' => true],
            ['priority' => 'ASC']
        );
    }

    /** @return list<ProviderDomainBinding> */
    public function findForProvider(Provider $provider): array
    {
        return $this->findBy(['provider' => $provider], ['priority' => 'ASC']);
    }
}

