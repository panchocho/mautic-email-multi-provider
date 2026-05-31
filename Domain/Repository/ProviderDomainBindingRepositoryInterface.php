<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderDomainBinding;

interface ProviderDomainBindingRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<ProviderDomainBinding> */
    public function findActiveByDomain(string $domain): array;

    /** @return list<ProviderDomainBinding> */
    public function findForProvider(Provider $provider): array;
}

