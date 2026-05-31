<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderId;

interface ProviderRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(ProviderId $id): ?Provider;

    public function findOneByCode(string $code): ?Provider;

    /** @return list<Provider> */
    public function findEnabled(): array;
}

