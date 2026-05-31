<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Maintenance;

use Doctrine\DBAL\Connection;
use DateTimeImmutable;
use DateTimeZone;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class QueueMaintenanceService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SmartMailerSettingsRepository $settingsRepository,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * @return array{
     *     delivery_logs_deleted: int,
     *     delivery_archive_deleted: int,
     *     health_history_deleted: int,
     *     retry_rows_promoted: int,
     *     retry_rows_expired: int,
     *     retry_rows_deleted: int
     * }
     */
    public function sweep(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $maintenance = $this->getMaintenanceConfig();
        $retryConfig = $this->getRetryConfig();

        $deliveryRetentionDays = max(
            1,
            $this->settingsRepository->getInt(
                'maintenance.delivery_log_retention_days',
                (int) ($maintenance['delivery_log_retention_days'] ?? 90)
            )
        );
        $deliveryArchiveRetentionDays = max(
            1,
            $this->settingsRepository->getInt(
                'maintenance.delivery_archive_retention_days',
                (int) ($maintenance['delivery_archive_retention_days'] ?? 180)
            )
        );
        $healthRetentionDays = max(
            1,
            $this->settingsRepository->getInt(
                'maintenance.health_history_retention_days',
                (int) ($maintenance['health_history_retention_days'] ?? 180)
            )
        );
        $retryRetentionDays = max(
            1,
            $this->settingsRepository->getInt(
                'maintenance.retry_retention_days',
                (int) ($maintenance['retry_retention_days'] ?? 30)
            )
        );
        $maxRetries = max(1, (int) ($retryConfig['max_retries'] ?? 8));

        $deliveryCutoff = $now->modify(sprintf('-%d days', $deliveryRetentionDays));
        $deliveryArchiveCutoff = $now->modify(sprintf('-%d days', $deliveryArchiveRetentionDays));
        $healthCutoff = $now->modify(sprintf('-%d days', $healthRetentionDays));
        $retryCutoff = $now->modify(sprintf('-%d days', $retryRetentionDays));

        $deliveryLogsDeleted = $this->connection->executeStatement(
            'DELETE FROM smr_delivery_log WHERE attempted_at < :cutoff',
            ['cutoff' => $deliveryCutoff->format('Y-m-d H:i:s')]
        );

        $healthHistoryDeleted = $this->connection->executeStatement(
            'DELETE FROM smr_provider_health_history WHERE checked_at < :cutoff',
            ['cutoff' => $healthCutoff->format('Y-m-d H:i:s')]
        );

        $deliveryArchiveDeleted = $this->connection->executeStatement(
            'DELETE FROM smr_delivery_log_archive WHERE archived_at < :cutoff',
            ['cutoff' => $deliveryArchiveCutoff->format('Y-m-d H:i:s')]
        );

        $retryRowsPromoted = 0;
        $retryRowsExpired = 0;
        $dueRetries = $this->connection->fetchAllAssociative(
            'SELECT id, attempt, status, next_attempt_at, created_at
             FROM smr_retry_queue
             WHERE status IN (\'pending\', \'retrying\')
               AND next_attempt_at <= :now
             ORDER BY next_attempt_at ASC
             LIMIT 5000',
            ['now' => $now->format('Y-m-d H:i:s')]
        );

        foreach ($dueRetries as $retryRow) {
            $retryId = (int) ($retryRow['id'] ?? 0);
            $attempt = (int) ($retryRow['attempt'] ?? 0);
            $createdAtRaw = (string) ($retryRow['created_at'] ?? '');
            $createdAt = $createdAtRaw !== '' ? new DateTimeImmutable($createdAtRaw, new DateTimeZone('UTC')) : $now;

            if ($attempt >= $maxRetries || $createdAt < $retryCutoff) {
                $this->connection->update('smr_retry_queue', [
                    'status' => 'dead',
                    'last_error' => 'expired_by_maintenance_sweep',
                ], ['id' => $retryId]);
                ++$retryRowsExpired;

                continue;
            }

            $this->connection->update('smr_retry_queue', [
                'status' => 'retrying',
                'next_attempt_at' => $now->format('Y-m-d H:i:s'),
            ], ['id' => $retryId]);
            ++$retryRowsPromoted;
        }

        $retryRowsDeleted = $this->connection->executeStatement(
            'DELETE FROM smr_retry_queue
             WHERE status = \'dead\'
               AND created_at < :cutoff',
            ['cutoff' => $retryCutoff->format('Y-m-d H:i:s')]
        );

        return [
            'delivery_logs_deleted' => $deliveryLogsDeleted,
            'delivery_archive_deleted' => $deliveryArchiveDeleted,
            'health_history_deleted' => $healthHistoryDeleted,
            'retry_rows_promoted' => $retryRowsPromoted,
            'retry_rows_expired' => $retryRowsExpired,
            'retry_rows_deleted' => $retryRowsDeleted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMaintenanceConfig(): array
    {
        if (!$this->parameterBag->has('smart_mailer_router.maintenance')) {
            return [];
        }

        $value = $this->parameterBag->get('smart_mailer_router.maintenance');

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRetryConfig(): array
    {
        if (!$this->parameterBag->has('smart_mailer_router.retry')) {
            return [];
        }

        $value = $this->parameterBag->get('smart_mailer_router.retry');

        return is_array($value) ? $value : [];
    }
}
