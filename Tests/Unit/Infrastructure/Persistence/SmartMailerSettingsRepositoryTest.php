<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use PHPUnit\Framework\TestCase;

final class SmartMailerSettingsRepositoryTest extends TestCase
{
    public function testAllReturnsDefaultValuesWhenTableIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('FROM smr_setting'))
            ->willReturn([]);

        $repository = new SmartMailerSettingsRepository($connection);
        $settings = $repository->all();

        self::assertSame('90', $settings['maintenance.delivery_log_retention_days']);
        self::assertSame('8', $settings['retry.max_retries']);
    }

    public function testSetManyPersistsAllValuesAndGetIntReadsSingleSetting(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->with(
                self::stringContains('INSERT INTO smr_setting'),
                self::isType('array')
            );
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::stringContains('FROM smr_setting'),
                ['setting_key' => 'maintenance.retry_retention_days']
            )
            ->willReturn('45');

        $repository = new SmartMailerSettingsRepository($connection);
        $repository->setMany([
            'maintenance.delivery_log_retention_days' => '90',
            'maintenance.retry_retention_days' => '45',
        ]);

        self::assertSame(45, $repository->getInt('maintenance.retry_retention_days', 30));
    }
}
