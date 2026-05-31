<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Maintenance;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Throwable;

final class DeliveryLogBulkActionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<int> $ids
     */
    public function archive(array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                'INSERT INTO smr_delivery_log_archive (
                    delivery_log_id,
                    provider_id,
                    routing_profile_id,
                    external_message_id,
                    recipient,
                    sender,
                    subject,
                    domain,
                    status,
                    attempt_no,
                    failover_count,
                    selection_reason,
                    http_status,
                    latency_ms,
                    failure_reason,
                    metadata,
                    attempted_at,
                    archived_at
                )
                SELECT
                    d.id,
                    d.provider_id,
                    d.routing_profile_id,
                    d.external_message_id,
                    d.recipient,
                    d.sender,
                    d.subject,
                    d.domain,
                    d.status,
                    d.attempt_no,
                    d.failover_count,
                    d.selection_reason,
                    d.http_status,
                    d.latency_ms,
                    d.failure_reason,
                    d.metadata,
                    d.attempted_at,
                    UTC_TIMESTAMP()
                FROM smr_delivery_log d
                WHERE d.id IN (:ids)
                ON DUPLICATE KEY UPDATE
                    provider_id = VALUES(provider_id),
                    routing_profile_id = VALUES(routing_profile_id),
                    external_message_id = VALUES(external_message_id),
                    recipient = VALUES(recipient),
                    sender = VALUES(sender),
                    subject = VALUES(subject),
                    domain = VALUES(domain),
                    status = VALUES(status),
                    attempt_no = VALUES(attempt_no),
                    failover_count = VALUES(failover_count),
                    selection_reason = VALUES(selection_reason),
                    http_status = VALUES(http_status),
                    latency_ms = VALUES(latency_ms),
                    failure_reason = VALUES(failure_reason),
                    metadata = VALUES(metadata),
                    attempted_at = VALUES(attempted_at),
                    archived_at = VALUES(archived_at)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::INTEGER]
            );

            $deleted = $this->connection->executeStatement(
                'DELETE FROM smr_delivery_log WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::INTEGER]
            );

            $this->connection->commit();

            return $deleted;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param list<int> $ids
     */
    public function purge(array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        return $this->connection->executeStatement(
            'DELETE FROM smr_delivery_log WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER]
        );
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => max(0, (int) $value),
            $ids
        ))));

        /** @var list<int> $normalized */
        return $normalized;
    }
}
