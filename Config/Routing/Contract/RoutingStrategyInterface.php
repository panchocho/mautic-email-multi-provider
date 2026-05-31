<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract;

interface RoutingStrategyInterface
{
    public function getName(): string;

    /**
     * @param list<string> $providers
     * @param array<string, mixed> $context
     */
    public function selectProvider(array $providers, array $context = []): ?string;
}

