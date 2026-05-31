<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model;

use DateTimeImmutable;

final class ProviderHealthSnapshot
{
    public function __construct(
        public readonly string $provider = '',
        public readonly float $score = 0.0,
        public readonly int $successCount = 0,
        public readonly int $failureCount = 0,
        public readonly float $bounceRate = 0.0,
        public readonly float $complaintRate = 0.0,
        public readonly int $p95LatencyMs = 0,
        public readonly DateTimeImmutable $updatedAt = new DateTimeImmutable()
    ) {
    }

    public function withScore(float $score): self
    {
        return new self(
            $this->provider,
            max(0.0, min(100.0, $score)),
            $this->successCount,
            $this->failureCount,
            $this->bounceRate,
            $this->complaintRate,
            $this->p95LatencyMs,
            new DateTimeImmutable()
        );
    }
}
