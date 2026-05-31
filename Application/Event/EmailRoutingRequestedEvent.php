<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class EmailRoutingRequestedEvent extends Event
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $tenantId,
        public readonly string $recipient,
        public readonly string $messageType,
        public readonly string $region,
        public readonly string $routingMode,
        public readonly array $payload = [],
        public readonly array $metadata = []
    ) {
    }
}

