<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Retry;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueBulkActionService;
use PHPUnit\Framework\TestCase;

final class RetryQueueBulkActionServiceTest extends TestCase
{
    public function testApplyNowRequeuesSelectedRetriesImmediately(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::stringContains('smr_retry_queue'),
                ['id' => 77]
            )
            ->willReturn(['id' => 77, 'attempt' => 3]);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'smr_retry_queue',
                self::callback(static function (array $data): bool {
                    return ($data['attempt'] ?? null) === 4
                        && ($data['status'] ?? null) === 'pending'
                        && ($data['next_attempt_at'] ?? null) === '2026-05-31 15:00:00'
                        && array_key_exists('last_error', $data)
                        && $data['last_error'] === null;
                }),
                ['id' => 77]
            );

        $service = new RetryQueueBulkActionService($connection);
        $result = $service->apply([77], 'now', new DateTimeImmutable('2026-05-31 15:00:00'));

        self::assertSame(1, $result);
    }

    public function testApplyDiscardDeletesSelectedRetries(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('DELETE FROM smr_retry_queue'),
                self::callback(static function (array $params): bool {
                    return array_values($params['ids'] ?? []) === [8, 9];
                }),
                self::isType('array')
            )
            ->willReturn(2);

        $service = new RetryQueueBulkActionService($connection);

        self::assertSame(2, $service->apply([8, 9], 'discard'));
    }
}
