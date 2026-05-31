<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Delivery;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;

final class RouteEmailExecution
{
    public function __construct(
        public readonly string $primaryProvider,
        public readonly ?string $providerCode,
        public readonly ProviderSendResult $result,
    ) {
    }
}
