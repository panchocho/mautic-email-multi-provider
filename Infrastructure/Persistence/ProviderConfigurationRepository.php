<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use JsonException;

final class ProviderConfigurationRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveProvider(string $providerType, ?string $providerCode = null): ?array
    {
        $providerType = strtolower(trim($providerType));
        if ($providerType === '') {
            return null;
        }

        $sql = 'SELECT id, name, code, provider_type, enabled, weight, priority, throughput_limit, cost_per_email, reputation, health_score, tags, notes, quarantined_until, config
                FROM smr_provider
                WHERE provider_type = :provider_type AND enabled = 1';
        $params = ['provider_type' => $providerType];

        if (is_string($providerCode) && trim($providerCode) !== '') {
            $sql .= ' AND code = :code';
            $params['code'] = strtolower(trim($providerCode));
        }

        $sql .= ' ORDER BY priority DESC, weight DESC, id ASC LIMIT 1';

        $row = $this->connection->fetchAssociative($sql, $params);
        if (!is_array($row) && isset($params['code'])) {
            unset($params['code']);
            $sql = 'SELECT id, name, code, provider_type, enabled, weight, priority, throughput_limit, cost_per_email, reputation, health_score, tags, notes, quarantined_until, config
                    FROM smr_provider
                    WHERE provider_type = :provider_type AND enabled = 1
                    ORDER BY priority DESC, weight DESC, id ASC LIMIT 1';
            $row = $this->connection->fetchAssociative($sql, $params);
        }
        if (!is_array($row)) {
            return null;
        }

        $tags = [];
        try {
            $decodedTags = json_decode((string) ($row['tags'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decodedTags)) {
                $tags = array_values(array_filter(array_map('strval', $decodedTags), static fn (string $tag): bool => trim($tag) !== ''));
            }
        } catch (JsonException) {
            $tags = [];
        }

        $config = [];
        try {
            $decodedConfig = json_decode((string) ($row['config'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decodedConfig)) {
                $config = $decodedConfig;
            }
        } catch (JsonException) {
            $config = [];
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'provider_type' => (string) ($row['provider_type'] ?? ''),
            'enabled' => (int) ($row['enabled'] ?? 0),
            'weight' => (int) ($row['weight'] ?? 0),
            'priority' => (int) ($row['priority'] ?? 0),
            'throughput_limit' => (int) ($row['throughput_limit'] ?? 0),
            'cost_per_email' => (float) ($row['cost_per_email'] ?? 0.0),
            'reputation' => (float) ($row['reputation'] ?? 0.0),
            'health_score' => (int) ($row['health_score'] ?? 0),
            'tags' => $tags,
            'notes' => $row['notes'] ?? null,
            'quarantined_until' => $row['quarantined_until'] ?? null,
            'config' => $config,
        ];
    }
}
