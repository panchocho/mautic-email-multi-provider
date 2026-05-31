<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing;

use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Registry\RoutingStrategyRegistry;

final class SmartMailRouter
{
    public function __construct(
        private readonly RoutingStrategyRegistry $strategyRegistry,
        private readonly string $defaultStrategy = 'round_robin',
    ) {
    }

    /**
     * @param list<string> $providers
     * @param array<string, mixed> $context
     */
    public function selectProvider(array $providers, array $context = [], ?string $strategyName = null): ?string
    {
        $strategy = $this->strategyRegistry->get($strategyName ?? $this->defaultStrategy);

        return $strategy->selectProvider($providers, $context);
    }
}

