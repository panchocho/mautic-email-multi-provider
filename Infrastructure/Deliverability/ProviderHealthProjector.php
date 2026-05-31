<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Deliverability;

use DateTimeImmutable;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;

final class ProviderHealthProjector
{
    public function __construct(
        private readonly ProviderHealthRepositoryInterface $healthRepository,
        private readonly DeliveryEventSignalExtractor $signalExtractor
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public function ingest(array $event): ProviderHealthSnapshot
    {
        $signal = $this->signalExtractor->extract($event);
        $provider = $signal['provider'];

        $snapshot = $this->healthRepository->get($provider) ?? new ProviderHealthSnapshot(
            provider: $provider,
            score: 50.0,
            successCount: 0,
            failureCount: 0,
            bounceRate: 0.0,
            complaintRate: 0.0,
            p95LatencyMs: 0,
            updatedAt: new DateTimeImmutable()
        );

        $successCount = $snapshot->successCount;
        $failureCount = $snapshot->failureCount;
        $bounceCount = (int) round($snapshot->bounceRate * max(1, $successCount + $failureCount));
        $complaintCount = (int) round($snapshot->complaintRate * max(1, $successCount + $failureCount));

        if ($signal['delivered'] || in_array($signal['status'], ['accepted', 'opened', 'open', 'clicked', 'click'], true)) {
            $successCount++;
        } elseif (!$signal['deferred']) {
            $failureCount++;
        } else {
            $failureCount++;
        }

        if ($signal['bounce']) {
            $bounceCount++;
        }

        if ($signal['complaint']) {
            $complaintCount++;
        }

        $total = max(1, $successCount + $failureCount);
        $p95Latency = $this->rollingLatency($snapshot->p95LatencyMs, $signal['latency_ms']);

        $updated = new ProviderHealthSnapshot(
            provider: $provider,
            score: $snapshot->score,
            successCount: $successCount,
            failureCount: $failureCount,
            bounceRate: $bounceCount / $total,
            complaintRate: $complaintCount / $total,
            p95LatencyMs: $p95Latency,
            updatedAt: new DateTimeImmutable()
        );

        $this->healthRepository->put($updated);

        return $updated;
    }

    private function rollingLatency(int $previousP95, int $observedLatency): int
    {
        if ($observedLatency <= 0) {
            return $previousP95;
        }

        if ($previousP95 <= 0) {
            return $observedLatency;
        }

        return (int) round(($previousP95 * 0.9) + ($observedLatency * 0.1));
    }
}
