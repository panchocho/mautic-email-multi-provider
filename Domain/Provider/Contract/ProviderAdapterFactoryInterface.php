<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract;

interface ProviderAdapterFactoryInterface
{
    public function create(string $provider): ProviderAdapterInterface;

    /**
     * @return array<string, ProviderAdapterInterface>
     */
    public function all(): array;
}

