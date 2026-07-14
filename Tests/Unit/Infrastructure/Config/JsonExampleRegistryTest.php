<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Infrastructure\Config;

use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Config\JsonExampleRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use PHPUnit\Framework\TestCase;

final class JsonExampleRegistryTest extends TestCase
{
    public function testReturnsModeSpecificProfileExamples(): void
    {
        $registry = new JsonExampleRegistry(new ProviderConfigSchemaRegistry());

        self::assertSame(
            ['allowed_domains' => ['gmail.com', 'yahoo.com']],
            $registry->profileConfig('domain_routing')
        );

        self::assertSame(
            ['percentage_split' => ['brevo' => 60, 'resend' => 40]],
            $registry->profileConfig('percentage_split')
        );

        self::assertSame([], $registry->profileConfig('round_robin'));
    }

    public function testReturnsRuleConstraintExample(): void
    {
        $registry = new JsonExampleRegistry(new ProviderConfigSchemaRegistry());

        self::assertSame(
            [
                'tenant_id' => 'tenant-a',
                'campaign_type' => 'transactional',
                'region' => 'us',
                'message_type' => 'transactional',
                'priority_min' => 0,
                'priority_max' => 100,
                'recipient_domain' => '*.example.com',
                'metadata' => [
                    'source' => 'example',
                ],
            ],
            $registry->ruleConstraints()
        );
    }

    public function testPrettyJsonOrExampleFallsBackToExampleWhenEmpty(): void
    {
        $registry = new JsonExampleRegistry(new ProviderConfigSchemaRegistry());

        self::assertSame(
            json_encode(['foo' => 'bar'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $registry->prettyJsonOrExample('', ['foo' => 'bar'])
        );
    }

    public function testAppliesUiJsonExampleOverridesFromSettings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(json_encode([
            'providers' => [
                'brevo' => [
                    'transport' => 'api',
                    'api_key' => 'override-key',
                    'sender_email' => 'custom@example.com',
                    'sender_name' => 'Custom',
                ],
            ],
            'profiles' => [
                'domain_routing' => [
                    'allowed_domains' => ['example.com'],
                ],
            ],
            'rules' => [
                'constraints_json' => [
                    'tenant_id' => 'tenant-custom',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $settingsRepository = new SmartMailerSettingsRepository($connection);
        $registry = new JsonExampleRegistry(new ProviderConfigSchemaRegistry(), $settingsRepository);

        self::assertSame('override-key', $registry->providerConfig('brevo')['api_key']);
        self::assertSame(['allowed_domains' => ['example.com']], $registry->profileConfig('domain_routing'));
        self::assertSame('tenant-custom', $registry->ruleConstraints()['tenant_id']);
    }

    public function testFallsBackToBaseExamplesWhenSettingsCannotBeRead(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('Table missing'));

        $settingsRepository = new SmartMailerSettingsRepository($connection);
        $registry = new JsonExampleRegistry(new ProviderConfigSchemaRegistry(), $settingsRepository);

        self::assertSame('xkeysib-REEMPLAZAR', $registry->providerConfig('brevo')['api_key']);
        self::assertSame(['allowed_domains' => ['gmail.com', 'yahoo.com']], $registry->profileConfig('domain_routing'));
        self::assertArrayHasKey('tenant_id', $registry->ruleConstraints());
    }
}
