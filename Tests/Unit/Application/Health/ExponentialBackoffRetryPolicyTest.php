<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Health;

use MauticPlugin\SmartMailerRouterBundle\Application\Health\ExponentialBackoffRetryPolicy;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ExponentialBackoffRetryPolicyTest extends TestCase
{
    public function testPolicyReadsRetryConfigurationFromSettings(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('fetchOne')->willReturnMap([
            ['SELECT setting_value FROM smr_setting WHERE setting_key = :setting_key', ['setting_key' => 'retry.max_retries'], '3'],
            ['SELECT setting_value FROM smr_setting WHERE setting_key = :setting_key', ['setting_key' => 'retry.base_delay_ms'], '1000'],
            ['SELECT setting_value FROM smr_setting WHERE setting_key = :setting_key', ['setting_key' => 'retry.max_delay_ms'], '5000'],
            ['SELECT setting_value FROM smr_setting WHERE setting_key = :setting_key', ['setting_key' => 'retry.multiplier'], '1.5'],
        ]);
        $settingsRepository = new SmartMailerSettingsRepository($connection);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('has')->willReturn(true);
        $parameterBag->method('get')->willReturn([
            'max_retries' => 5,
            'base_delay_ms' => 5000,
            'multiplier' => 2.0,
            'max_delay_ms' => 300000,
        ]);

        $policy = new ExponentialBackoffRetryPolicy($settingsRepository, $parameterBag);

        self::assertTrue($policy->shouldRetry(1, 'temporary_failure'));
        self::assertFalse($policy->shouldRetry(3, 'temporary_failure'));
        self::assertSame(5, $policy->nextDelaySeconds(1, 'temporary_failure'));
    }

    public function testTerminalReasonsDoNotRetry(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params): string|false {
            return match ($params['setting_key'] ?? '') {
                'retry.max_retries' => '5',
                'retry.base_delay_ms' => '5000',
                'retry.max_delay_ms' => '300000',
                'retry.multiplier' => '2.0',
                default => false,
            };
        });
        $settingsRepository = new SmartMailerSettingsRepository($connection);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('has')->willReturn(true);
        $parameterBag->method('get')->willReturn([
            'max_retries' => 5,
            'base_delay_ms' => 5000,
            'multiplier' => 2.0,
            'max_delay_ms' => 300000,
        ]);

        $policy = new ExponentialBackoffRetryPolicy($settingsRepository, $parameterBag);

        self::assertFalse($policy->shouldRetry(1, 'hard_bounce'));
        self::assertSame(0, $policy->nextDelaySeconds(1, 'hard_bounce'));
    }
}
