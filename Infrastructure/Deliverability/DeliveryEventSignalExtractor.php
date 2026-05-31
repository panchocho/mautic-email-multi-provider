<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Deliverability;

final class DeliveryEventSignalExtractor
{
    /**
     * @param array<string, mixed> $event
     *
     * @return array{
     *   provider: string,
     *   status: string,
     *   latency_ms: int,
     *   bounce: bool,
     *   complaint: bool,
     *   delivered: bool,
     *   deferred: bool,
     *   opened: bool,
     *   clicked: bool,
     *   failed: bool
     * }
     */
    public function extract(array $event): array
    {
        $status = strtolower((string) ($event['status'] ?? 'unknown'));
        $delivered = $status === 'delivered' || $status === 'sent' || (bool) ($event['delivered'] ?? false);
        $deferred = $status === 'deferred' || (bool) ($event['deferred'] ?? false);
        $opened = $status === 'open' || $status === 'opened' || (bool) ($event['open'] ?? false);
        $clicked = $status === 'click' || $status === 'clicked' || (bool) ($event['click'] ?? false);

        return [
            'provider' => (string) ($event['provider'] ?? 'unknown'),
            'status' => $status,
            'latency_ms' => max(0, (int) ($event['latency_ms'] ?? 0)),
            'bounce' => $status === 'bounced' || (bool) ($event['bounce'] ?? false),
            'complaint' => $status === 'complaint' || (bool) ($event['complaint'] ?? false),
            'delivered' => $delivered,
            'deferred' => $deferred,
            'opened' => $opened,
            'clicked' => $clicked,
            'failed' => in_array($status, ['bounced', 'complaint', 'failed', 'rejected', 'deferred'], true),
        ];
    }
}
