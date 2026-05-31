<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Health;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;

final class AutoActionsService
{
    public function __construct(
        private readonly ProviderHealthRepositoryInterface $healthRepository,
        private readonly Connection $connection,
        private readonly float $pauseThreshold = 25.0,
        private readonly float $degradeThreshold = 45.0,
        private readonly float $restoreThreshold = 70.0
    ) {
    }

    /**
     * @return list<array{provider: string, action: string, reason: string}>
     */
    public function apply(?string $provider = null): array
    {
        $actions = [];
        $snapshots = $provider !== null
            ? array_filter(
                $this->healthRepository->all(),
                static fn (string $key): bool => strcasecmp($key, $provider) === 0,
                ARRAY_FILTER_USE_KEY
            )
            : $this->healthRepository->all();

        foreach ($snapshots as $snapshot) {
            if ($snapshot->score < $this->pauseThreshold) {
                $this->pauseProvider($snapshot);
                $actions[] = [
                    'provider' => $snapshot->provider,
                    'action' => 'pause_provider',
                    'reason' => 'health score below pause threshold',
                ];
                continue;
            }

            if ($snapshot->score < $this->degradeThreshold) {
                $this->degradeProvider($snapshot);
                $actions[] = [
                    'provider' => $snapshot->provider,
                    'action' => 'reduce_traffic',
                    'reason' => 'health score below degrade threshold',
                ];
                continue;
            }

            $this->restoreProvider($snapshot);
            $actions[] = [
                'provider' => $snapshot->provider,
                'action' => 'normal_traffic',
                'reason' => 'health score acceptable',
            ];
        }

        return $actions;
    }

    private function pauseProvider(ProviderHealthSnapshot $snapshot): void
    {
        $providerId = $this->resolveProviderId($snapshot->provider);
        if ($providerId === null) {
            return;
        }

        $this->healthRepository->put($snapshot->withScore(max(0.0, $snapshot->score - 15.0)));

        $this->connection->update('smr_provider', [
            'enabled' => 0,
            'quarantined_until' => (new DateTimeImmutable('+6 hours'))->format('Y-m-d H:i:s'),
            'health_score' => (int) round(max(0.0, $snapshot->score - 15.0)),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $providerId]);

        $this->writeHistory($providerId, 'quarantined', 'Automatic quarantine triggered by low health score.', [
            'score' => $snapshot->score,
            'threshold' => $this->pauseThreshold,
            'automatic' => true,
        ]);
    }

    private function degradeProvider(ProviderHealthSnapshot $snapshot): void
    {
        $degradedScore = max(0.0, $snapshot->score - 10.0);
        $providerId = $this->resolveProviderId($snapshot->provider);
        if ($providerId === null) {
            return;
        }

        $this->healthRepository->put($snapshot->withScore($degradedScore));

        $this->connection->update('smr_provider', [
            'enabled' => 1,
            'health_score' => (int) round($degradedScore),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $providerId]);

        $this->writeHistory($providerId, 'degraded', 'Automatic traffic reduction triggered by low health score.', [
            'score' => $snapshot->score,
            'threshold' => $this->degradeThreshold,
            'automatic' => true,
        ]);
    }

    private function restoreProvider(ProviderHealthSnapshot $snapshot): void
    {
        $restoredScore = min(100.0, max($snapshot->score, $this->restoreThreshold));
        $providerId = $this->resolveProviderId($snapshot->provider);
        if ($providerId === null) {
            return;
        }

        $this->healthRepository->put($snapshot->withScore($restoredScore));

        $this->connection->update('smr_provider', [
            'enabled' => 1,
            'quarantined_until' => null,
            'health_score' => (int) round($restoredScore),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $providerId]);

        $this->writeHistory($providerId, 'restored', 'Automatic restore triggered by healthy score.', [
            'score' => $snapshot->score,
            'threshold' => $this->restoreThreshold,
            'automatic' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function writeHistory(string $providerId, string $status, string $message, array $details): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->insert('smr_provider_health_history', [
            'provider_id' => $providerId,
            'checked_at' => $now,
            'score' => (int) round(($details['score'] ?? 0.0)),
            'status' => $status,
            'message' => $message,
            'details' => json_encode($details, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function resolveProviderId(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id FROM smr_provider WHERE code = :code OR name = :name ORDER BY priority DESC, id ASC LIMIT 1',
            ['code' => $provider, 'name' => $provider]
        );

        if (!is_array($row) || !isset($row['id']) || !is_string($row['id']) || trim($row['id']) === '') {
            $row = $this->connection->fetchAssociative(
                'SELECT id FROM smr_provider WHERE provider_type = :provider_type ORDER BY priority DESC, id ASC LIMIT 1',
                ['provider_type' => $provider]
            );

            if (!is_array($row) || !isset($row['id']) || !is_string($row['id']) || trim($row['id']) === '') {
                return null;
            }
        }

        return $row['id'];
    }
}
