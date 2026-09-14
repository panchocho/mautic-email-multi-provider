<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

final class SmartMailerSettingsRepository
{
    /**
     * @return array<string, string>
     */
    public static function defaultValues(): array
    {
        return [
            'router.active' => '1',
            'maintenance.delivery_log_retention_days' => '90',
            'maintenance.delivery_archive_retention_days' => '180',
            'maintenance.health_history_retention_days' => '180',
            'maintenance.retry_retention_days' => '30',
            'retry.max_retries' => '8',
            'retry.base_delay_ms' => '2000',
            'retry.multiplier' => '2.0',
            'retry.max_delay_ms' => '300000',
            'ui.json_examples' => '{}',
        ];
    }

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT setting_key, setting_value FROM smr_setting ORDER BY setting_key ASC'
        );

        $settings = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['setting_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }

        return array_replace(self::defaultValues(), $settings);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $key = trim($key);
        if ($key === '') {
            return $default;
        }

        $value = $this->connection->fetchOne(
            'SELECT setting_value FROM smr_setting WHERE setting_key = :setting_key',
            ['setting_key' => $key]
        );

        if ($value === false) {
            return $default ?? (self::defaultValues()[$key] ?? null);
        }

        return (string) $value;
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key);
        if ($value === null || trim($value) === '' || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public function getFloat(string $key, float $default): float
    {
        $value = $this->get($key);
        if ($value === null || trim($value) === '' || !is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    public function isActive(): bool
    {
        $value = strtolower(trim((string) $this->get('router.active', '1')));

        return !in_array($value, ['', '0', 'false', 'off', 'no', 'disabled'], true);
    }

    /**
     * @param array<string, scalar|null> $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value === null ? null : (string) $value);
        }
    }

    public function set(string $key, ?string $value): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO smr_setting (setting_key, setting_value, updated_at)
             VALUES (:setting_key, :setting_value, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               setting_value = VALUES(setting_value),
               updated_at = VALUES(updated_at)',
            [
                'setting_key' => $key,
                'setting_value' => $value,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $key, array $default = []): array
    {
        $value = $this->get($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $default;
        }

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function setJson(string $key, array $value): void
    {
        $this->set($key, json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
