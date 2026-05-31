<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Maintenance;

use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\DeliveryLogBulkActionService;
use PHPUnit\Framework\TestCase;

final class DeliveryLogBulkActionServiceTest extends TestCase
{
    public function testArchiveMovesRowsToArchiveTableAndDeletesSourceRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');

        $calls = 0;
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = [], array $types = []) use (&$calls): int {
                ++$calls;

                if ($calls === 1) {
                    self::assertStringContainsString('smr_delivery_log_archive', $sql);
                    self::assertEqualsCanonicalizing([1, 2], array_values($params['ids'] ?? []));

                    return 2;
                }

                self::assertStringContainsString('DELETE FROM smr_delivery_log', $sql);
                self::assertEqualsCanonicalizing([1, 2], array_values($params['ids'] ?? []));

                return 2;
            });

        $service = new DeliveryLogBulkActionService($connection);
        $deleted = $service->archive([2, 1, 2]);

        self::assertSame(2, $deleted);
    }

    public function testPurgeDeletesRowsWithoutArchivingThem(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('DELETE FROM smr_delivery_log'),
                self::callback(static function (array $params): bool {
                    return array_values($params['ids'] ?? []) === [5, 6];
                }),
                self::isType('array')
            )
            ->willReturn(2);

        $service = new DeliveryLogBulkActionService($connection);

        self::assertSame(2, $service->purge([5, 6]));
    }
}
