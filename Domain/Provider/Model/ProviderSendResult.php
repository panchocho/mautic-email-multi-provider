<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model;

final class ProviderSendResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $provider = '',
        public readonly bool $accepted = false,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $reason = null,
        public readonly int $retryAfterSeconds = 0,
        public readonly array $metadata = []
    ) {
    }
}
