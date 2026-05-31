<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\ProviderRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderId;

final class ProviderRepository extends AbstractDoctrineRepository implements ProviderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Provider::class);
    }

    public function findById(ProviderId $id): ?Provider
    {
        return $this->find($id->toString());
    }

    public function findOneByCode(string $code): ?Provider
    {
        return $this->findOneBy(['code' => $code]);
    }

    /** @return list<Provider> */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }
}

