<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterFactoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;

final class RoutingContextFactory
{
    public function __construct(
        private readonly ProviderAdapterFactoryInterface $adapterFactory,
        private readonly ProviderHealthRepositoryInterface $healthRepository
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $statsByProvider
     * @param array<string, mixed> $metadata
     */
    public function create(array $statsByProvider = [], array $metadata = []): RoutingContext
    {
        $providers = [];
        foreach ($this->adapterFactory->all() as $provider => $adapter) {
            $providers[$provider] = $adapter->getProfile();
        }

        return new RoutingContext(
            providers: $providers,
            health: $this->healthRepository->all(),
            stats: $statsByProvider,
            metadata: $metadata
        );
    }
}

