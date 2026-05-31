<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\BrevoAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\MailgunAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\PostalAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\ResendAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\SendGridAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\SmtpOnlyAdapter;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter\SparkPostAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProviderAdaptersTest extends TestCase
{
    public function testSendGridAdapterExtractsMessageIdFromHeader(): void
    {
        $adapter = new SendGridAdapter(new MockHttpClient(
            new MockResponse('', [
                'http_code' => 202,
                'response_headers' => [
                    'x-message-id: sg-123',
                ],
            ])
        ));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'body' => 'World',
            'provider_config' => [
                'api_key' => 'SG.key',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('sg-123', $result->providerMessageId);
    }

    public function testMailgunAdapterExtractsResponseId(): void
    {
        $adapter = new MailgunAdapter(new MockHttpClient(new MockResponse(json_encode([
            'id' => 'mailgun-123',
            'message' => 'Queued. Thank you.',
        ], JSON_UNESCAPED_SLASHES) ?: '{}', [
            'http_code' => 200,
        ])));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'html' => '<p>World</p>',
            'provider_config' => [
                'api_key' => 'key-123',
                'domain' => 'mg.example.com',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('mailgun-123', $result->providerMessageId);
    }

    public function testPostalAdapterExtractsMessageIdFromNestedData(): void
    {
        $adapter = new PostalAdapter(new MockHttpClient(new MockResponse(json_encode([
            'status' => 'success',
            'data' => ['message_id' => 'postal-123'],
        ], JSON_UNESCAPED_SLASHES) ?: '{}', [
            'http_code' => 200,
        ])));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'text' => 'World',
            'provider_config' => [
                'api_key' => 'postal-key',
                'base_url' => 'https://postal.example.com',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('postal-123', $result->providerMessageId);
    }

    public function testResendAdapterExtractsIdFromResponse(): void
    {
        $adapter = new ResendAdapter(new MockHttpClient(new MockResponse(json_encode([
            'id' => 'resend-123',
        ], JSON_UNESCAPED_SLASHES) ?: '{}', [
            'http_code' => 200,
        ])));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'html' => '<p>World</p>',
            'provider_config' => [
                'api_key' => 're_123',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('resend-123', $result->providerMessageId);
    }

    public function testSparkPostAdapterExtractsTransmissionId(): void
    {
        $adapter = new SparkPostAdapter(new MockHttpClient(new MockResponse(json_encode([
            'results' => ['id' => 'spark-123'],
        ], JSON_UNESCAPED_SLASHES) ?: '{}', [
            'http_code' => 200,
        ])));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'text' => 'World',
            'provider_config' => [
                'api_key' => 'sp-key',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('spark-123', $result->providerMessageId);
    }

    public function testBrevoAdapterUsesApiResponseMessageId(): void
    {
        $adapter = new BrevoAdapter(new MockHttpClient(new MockResponse(json_encode([
            'messageId' => 'brevo-123',
        ], JSON_UNESCAPED_SLASHES) ?: '{}', [
            'http_code' => 201,
        ])));

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'text' => 'World',
            'provider_config' => [
                'api_key' => 'brevo-key',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertTrue($result->accepted);
        self::assertSame('brevo-123', $result->providerMessageId);
    }

    public function testSmtpOnlyAdapterSupportsForcedFailureWithoutTransport(): void
    {
        $adapter = new SmtpOnlyAdapter();

        $result = $adapter->queueSend($this->request(), [
            'subject' => 'Hello',
            'text' => 'World',
            'force_failure_provider' => 'smtp_only',
            'provider_config' => [
                'transport' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'user',
                'password' => 'pass',
                'sender_email' => 'sender@example.com',
            ],
        ]);

        self::assertFalse($result->accepted);
        self::assertSame('forced_failure', $result->reason);
    }

    private function request(): RoutingRequest
    {
        return new RoutingRequest(
            requestId: 'req-1',
            tenantId: 'tenant-a',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 10,
            recipient: 'user@example.com',
            metadata: []
        );
    }
}
