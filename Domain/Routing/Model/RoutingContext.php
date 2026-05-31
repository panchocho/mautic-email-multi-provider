<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;

final class RoutingContext
{
    /**
     * @param array<string, ProviderProfile> $providers
     * @param array<string, ProviderHealthSnapshot> $health
     * @param array<string, array<string, mixed>> $stats
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly array $providers,
        public readonly array $health = [],
        public readonly array $stats = [],
        public readonly array $metadata = []
    ) {
    }
}

