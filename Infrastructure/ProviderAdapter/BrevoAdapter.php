<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BrevoAdapter extends AbstractProviderAdapter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct(new ProviderProfile(
            id: 'brevo',
            name: 'brevo',
            type: ProviderType::BREVO,
            enabled: true,
            weight: 88,
            priority: 100,
            throughputLimit: 3500,
            domains: [],
            costPerEmail: 0.00009,
            reputation: 89.0,
            healthScore: 91.0,
            tags: ['marketing', 'api'],
            supportedMessageTypes: ['marketing', 'transactional'],
            supportedRegions: ['us', 'eu']
        ));
    }

    public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult
    {
        $forcedFailure = $payload['force_failure_provider'] ?? null;
        if (is_string($forcedFailure) && strcasecmp($forcedFailure, $this->getProfile()->name) === 0) {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'forced_failure',
                retryAfterSeconds: 15
            );
        }

        $configResult = $this->resolveBrevoConfig($payload);
        if ($configResult['ok'] === false) {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: (string) $configResult['reason'],
                retryAfterSeconds: 60
            );
        }

        /** @var array<string, mixed> $config */
        $config = $configResult['config'];
        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'brevo_smtp',
                null,
                ['provider_config_code' => $configResult['provider_code'] ?? null]
            );
        }

        $required = $this->validateConfig($config, ['api_key', 'sender_email']);
        if ($required !== null) {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'brevo_' . $required,
                retryAfterSeconds: 60
            );
        }

        $apiKey = (string) ($config['api_key'] ?? '');
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.brevo.com/v3'), '/');
        $senderEmail = (string) ($payload['from_email'] ?? $config['sender_email'] ?? '');
        $senderName = (string) ($payload['from_name'] ?? $config['sender_name'] ?? '');

        if ($senderEmail === '') {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'sender_not_configured',
                retryAfterSeconds: 0
            );
        }

        $subject = (string) ($payload['subject'] ?? '(no subject)');
        $htmlContent = (string) ($payload['html'] ?? $payload['html_content'] ?? '');
        $textContent = (string) ($payload['text'] ?? $payload['text_content'] ?? '');
        if ($htmlContent === '' && $textContent === '') {
            $textContent = (string) ($payload['body'] ?? '');
        }
        if ($htmlContent === '' && $textContent === '') {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'empty_message_body',
                retryAfterSeconds: 0
            );
        }

        $brevoPayload = [
            'sender' => array_filter([
                'email' => $senderEmail,
                'name' => $senderName !== '' ? $senderName : null,
            ]),
            'to' => [[
                'email' => $request->recipient,
                'name' => (string) ($payload['to_name'] ?? ''),
            ]],
            'subject' => $subject,
        ];
        if ($htmlContent !== '') {
            $brevoPayload['htmlContent'] = $htmlContent;
        }
        if ($textContent !== '') {
            $brevoPayload['textContent'] = $textContent;
        }
        if (isset($payload['reply_to']) && is_string($payload['reply_to']) && $payload['reply_to'] !== '') {
            $brevoPayload['replyTo'] = ['email' => $payload['reply_to']];
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/smtp/email', [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'api-key' => $apiKey,
                ],
                'json' => $brevoPayload,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface) {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'brevo_transport_error',
                retryAfterSeconds: 60
            );
        }

        $decoded = null;
        if ($body !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $decoded = null;
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return new ProviderSendResult(
                provider: $this->getProfile()->name,
                accepted: false,
                reason: 'brevo_http_'.$statusCode,
                retryAfterSeconds: $statusCode >= 500 ? 60 : 0,
                metadata: [
                    'status_code' => $statusCode,
                    'response' => is_array($decoded) ? $decoded : substr($body, 0, 500),
                ]
            );
        }

        $messageId = null;
        if (is_array($decoded) && isset($decoded['messageId']) && is_string($decoded['messageId'])) {
            $messageId = $decoded['messageId'];
        }
        if ($messageId === null || $messageId === '') {
            $messageId = strtolower($this->getProfile()->name) . '-' . md5($request->requestId . $request->recipient);
        }

        return new ProviderSendResult(
            provider: $this->getProfile()->name,
            accepted: true,
            providerMessageId: $messageId,
            metadata: [
                'status_code' => $statusCode,
                'region' => $request->region,
                'message_type' => $request->messageType,
                'provider_config_code' => $configResult['provider_code'] ?? null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, reason?: string, config?: array<string, mixed>, provider_code?: string}
     */
    private function resolveBrevoConfig(array $payload): array
    {
        if (isset($payload['provider_config']) && is_array($payload['provider_config'])) {
            return [
                'ok' => true,
                'config' => $payload['provider_config'],
                'provider_code' => (string) ($payload['provider_config_code'] ?? $payload['provider_code'] ?? ''),
            ];
        }

        return ['ok' => false, 'reason' => 'provider_config_not_found'];
    }
}
