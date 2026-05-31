<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\QueueMaintenanceService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class QueueMaintenanceServiceTest extends TestCase
{
    public function testSweepUsesConfiguredRetentionsAndPurgesArchiveTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(5, 6, 7, 8);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('FROM smr_retry_queue'), self::isType('array'))
            ->willReturn([]);

        $settingsConnection = $this->createMock(Connection::class);
        $settingsConnection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params): string|false {
            return match ($params['setting_key'] ?? '') {
                'maintenance.delivery_log_retention_days' => '10',
                'maintenance.delivery_archive_retention_days' => '20',
                'maintenance.health_history_retention_days' => '30',
                'maintenance.retry_retention_days' => '40',
                default => false,
            };
        });
        $settingsRepository = new SmartMailerSettingsRepository($settingsConnection);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('has')->willReturnMap([
            ['smart_mailer_router.maintenance', true],
            ['smart_mailer_router.retry', true],
        ]);
        $parameterBag->method('get')->willReturnMap([
            ['smart_mailer_router.maintenance', [
                'delivery_log_retention_days' => 90,
                'delivery_archive_retention_days' => 180,
                'health_history_retention_days' => 180,
                'retry_retention_days' => 30,
            ]],
            ['smart_mailer_router.retry', [
                'max_retries' => 8,
            ]],
        ]);

        $service = new QueueMaintenanceService($connection, $settingsRepository, $parameterBag);
        $result = $service->sweep(new DateTimeImmutable('2026-05-31 15:00:00', new DateTimeZone('UTC')));

        self::assertSame(5, $result['delivery_logs_deleted']);
        self::assertSame(6, $result['health_history_deleted']);
        self::assertSame(7, $result['delivery_archive_deleted']);
        self::assertSame(0, $result['retry_rows_promoted']);
        self::assertSame(0, $result['retry_rows_expired']);
        self::assertSame(8, $result['retry_rows_deleted']);
    }
}
