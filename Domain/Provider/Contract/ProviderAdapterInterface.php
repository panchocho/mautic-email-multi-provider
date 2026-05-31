<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

interface ProviderAdapterInterface
{
    public function getProfile(): ProviderProfile;

    /**
     * @param array<string, mixed> $payload
     */
    public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult;

    /**
     * @param array<string, mixed> $event
     */
    public function ingestDeliveryEvent(array $event): void;
}

