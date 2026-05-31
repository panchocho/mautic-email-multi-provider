<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract\ProviderAdapterInterface;

final class NullProviderAdapter implements ProviderAdapterInterface
{
    public function getName(): string
    {
        return 'null';
    }

    public function supports(string $provider): bool
    {
        return $provider !== '';
    }
}

