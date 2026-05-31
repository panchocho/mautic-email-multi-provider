<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Infrastructure\ProviderConfig;

use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use PHPUnit\Framework\TestCase;

final class ProviderConfigSchemaRegistryTest extends TestCase
{
    public function testValidatesProviderSpecificConfig(): void
    {
        $registry = new ProviderConfigSchemaRegistry();

        $result = $registry->validate('sendgrid', [
            'transport' => 'api',
            'api_key' => 'SG.key',
            'sender_email' => 'no-reply@example.com',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame('api', $result['normalized']['transport']);
        self::assertSame('https://api.sendgrid.com', $registry->defaultTemplate('sendgrid')['base_url']);
    }

    public function testRejectsMissingSenderEmail(): void
    {
        $registry = new ProviderConfigSchemaRegistry();

        $result = $registry->validate('mailgun', [
            'api_key' => 'key-123',
            'domain' => 'mg.example.com',
        ]);

        self::assertFalse($result['valid']);
        self::assertSame('sender_email_invalid', $result['reason']);
    }

    public function testAcceptsSmtpTransportForApiProviders(): void
    {
        $registry = new ProviderConfigSchemaRegistry();

        $result = $registry->validate('brevo', [
            'transport' => 'smtp',
            'host' => 'smtp.brevo.com',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'smtp-pass',
            'sender_email' => 'no-reply@example.com',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame('smtp', $result['normalized']['transport']);
    }

    public function testAcceptsSmtpOnlyProvider(): void
    {
        $registry = new ProviderConfigSchemaRegistry();

        $result = $registry->validate('smtp_only', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'smtp-pass',
            'sender_email' => 'no-reply@example.com',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame('smtp', $result['normalized']['transport']);
        self::assertSame('smtp', $registry->defaultTemplate('smtp_only')['transport']);
    }
}
