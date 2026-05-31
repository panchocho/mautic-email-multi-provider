<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract\RoutingStrategyInterface;

final class RoundRobinRoutingStrategy implements RoutingStrategyInterface
{
    private int $cursor = -1;

    public function getName(): string
    {
        return 'round_robin';
    }

    public function selectProvider(array $providers, array $context = []): ?string
    {
        $cleanProviders = array_values(array_filter(
            $providers,
            static fn (mixed $provider): bool => is_string($provider) && $provider !== ''
        ));

        if ($cleanProviders === []) {
            return null;
        }

        $this->cursor = ($this->cursor + 1) % count($cleanProviders);

        return $cleanProviders[$this->cursor];
    }
}

