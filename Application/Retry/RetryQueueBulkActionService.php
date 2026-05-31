<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Retry;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class RetryQueueBulkActionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<int> $ids
     */
    public function apply(array $ids, string $action, ?DateTimeImmutable $now = null): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return match ($action) {
            'discard' => $this->connection->executeStatement(
                'DELETE FROM smr_retry_queue WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::INTEGER]
            ),
            'later' => $this->updateRetries($ids, $now->modify('+15 minutes')->format('Y-m-d H:i:s')),
            'now' => $this->updateRetries($ids, $now->format('Y-m-d H:i:s')),
            default => 0,
        };
    }

    /**
     * @param list<int> $ids
     */
    private function updateRetries(array $ids, string $nextAttemptAt): int
    {
        $updated = 0;

        $this->connection->beginTransaction();
        try {
            foreach ($ids as $retryId) {
                $existing = $this->connection->fetchAssociative(
                    'SELECT id, attempt FROM smr_retry_queue WHERE id = :id',
                    ['id' => $retryId]
                );
                if (!is_array($existing)) {
                    continue;
                }

                $this->connection->update('smr_retry_queue', [
                    'attempt' => (int) ($existing['attempt'] ?? 0) + 1,
                    'status' => 'pending',
                    'next_attempt_at' => $nextAttemptAt,
                    'last_error' => null,
                ], ['id' => $retryId]);
                ++$updated;
            }

            $this->connection->commit();

            return $updated;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
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
