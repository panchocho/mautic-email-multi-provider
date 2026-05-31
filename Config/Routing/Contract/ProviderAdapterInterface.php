<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract;

interface ProviderAdapterInterface
{
    public function getName(): string;

    public function supports(string $provider): bool;
}

