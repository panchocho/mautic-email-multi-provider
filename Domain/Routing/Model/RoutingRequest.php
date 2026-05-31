<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model;

use DateTimeImmutable;

final class RoutingRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $tenantId,
        public readonly string $campaignType,
        public readonly string $region,
        public readonly string $messageType,
        public readonly int $priority,
        public readonly string $recipient,
        public readonly array $metadata = [],
        public readonly DateTimeImmutable $occurredAt = new DateTimeImmutable('now')
    ) {
    }
}
