<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;

interface ProviderHealthRepositoryInterface
{
    public function get(string $provider): ?ProviderHealthSnapshot;

    /**
     * @return array<string, ProviderHealthSnapshot>
     */
    public function all(): array;

    public function put(ProviderHealthSnapshot $snapshot): void;
}

