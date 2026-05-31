<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Health;

use DateTimeImmutable;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;

final class HealthScoreService
{
    public function __construct(private readonly ProviderHealthRepositoryInterface $healthRepository)
    {
    }

    public function recomputeForProvider(string $provider): ?ProviderHealthSnapshot
    {
        $snapshot = $this->healthRepository->get($provider);
        if ($snapshot === null) {
            return null;
        }

        $updated = $this->recomputeFromSnapshot($snapshot);
        $this->healthRepository->put($updated);

        return $updated;
    }

    /**
     * @return array<string, ProviderHealthSnapshot>
     */
    public function recomputeAll(): array
    {
        $updated = [];
        foreach ($this->healthRepository->all() as $provider => $snapshot) {
            $newSnapshot = $this->recomputeFromSnapshot($snapshot);
            $this->healthRepository->put($newSnapshot);
            $updated[$provider] = $newSnapshot;
        }

        return $updated;
    }

    public function recomputeFromSnapshot(ProviderHealthSnapshot $snapshot): ProviderHealthSnapshot
    {
        $total = max(1, $snapshot->successCount + $snapshot->failureCount);
        $successRate = $snapshot->successCount / $total;
        $latencyPenalty = min(35.0, ((float) $snapshot->p95LatencyMs / 1000.0) * 10.0);
        $bouncePenalty = min(20.0, $snapshot->bounceRate * 200.0);
        $complaintPenalty = min(20.0, $snapshot->complaintRate * 250.0);

        $score = ($successRate * 100.0) - $latencyPenalty - $bouncePenalty - $complaintPenalty;
        $score = max(0.0, min(100.0, $score));

        return new ProviderHealthSnapshot(
            provider: $snapshot->provider,
            score: $score,
            successCount: $snapshot->successCount,
            failureCount: $snapshot->failureCount,
            bounceRate: $snapshot->bounceRate,
            complaintRate: $snapshot->complaintRate,
            p95LatencyMs: $snapshot->p95LatencyMs,
            updatedAt: new DateTimeImmutable()
        );
    }

    /**
     * @param array<string, float|int|bool> $signal
     */
    public function scoreFromSignal(array $signal): float
    {
        $accepted = (bool) ($signal['accepted'] ?? false);
        $latency = (float) ($signal['latency_ms'] ?? 0.0);
        $bounced = (bool) ($signal['bounced'] ?? false);
        $complaint = (bool) ($signal['complaint'] ?? false);
        $deferred = (bool) ($signal['deferred'] ?? false);

        $score = $accepted ? 100.0 : 45.0;
        $score -= min(30.0, $latency / 100.0);
        $score -= $bounced ? 30.0 : 0.0;
        $score -= $complaint ? 35.0 : 0.0;
        $score -= $deferred ? 10.0 : 0.0;

        return max(0.0, min(100.0, $score));
    }
}
