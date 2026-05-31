<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SparkPostAdapter extends AbstractProviderAdapter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct(new ProviderProfile(
            id: 'sparkpost',
            name: 'sparkpost',
            type: ProviderType::SPARKPOST,
            enabled: true,
            weight: 92,
            priority: 115,
            throughputLimit: 4200,
            domains: [],
            costPerEmail: 0.00011,
            reputation: 90.0,
            healthScore: 92.0,
            tags: ['transactional', 'analytics'],
            supportedMessageTypes: ['transactional', 'marketing'],
            supportedRegions: ['us', 'eu', 'apac']
        ));
    }

    public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult
    {
        if (($forcedFailure = $payload['force_failure_provider'] ?? null) !== null
            && is_string($forcedFailure)
            && strcasecmp($forcedFailure, $this->getProfile()->name) === 0
        ) {
            return $this->failureResult('forced_failure', 15);
        }

        /** @var array<string, mixed>|null $config */
        $config = isset($payload['provider_config']) && is_array($payload['provider_config'])
            ? $payload['provider_config']
            : null;
        if ($config === null) {
            return $this->failureResult('sparkpost_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'sparkpost_smtp',
                null,
                ['provider_config_code' => $payload['provider_config_code'] ?? null]
            );
        }

        $required = $this->validateConfig($config, ['api_key', 'sender_email']);
        if ($required !== null) {
            return $this->failureResult($required, 60);
        }

        $senderEmail = $this->resolveSenderEmail($payload, $config);
        if ($senderEmail === '') {
            return $this->failureResult('sender_not_configured', 0);
        }

        $parts = $this->resolveMessageParts($payload);
        if ($parts['html'] === '' && $parts['text'] === '') {
            return $this->failureResult('empty_message_body', 0);
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.sparkpost.com/api/v1'), '/');
        $senderName = $this->resolveSenderName($payload, $config);
        $body = [
            'options' => array_filter([
                'open_tracking' => true,
                'click_tracking' => true,
                'sandbox' => (bool) ($config['sandbox'] ?? false),
            ]),
            'content' => array_filter([
                'from' => $senderName !== '' ? sprintf('%s <%s>', $senderName, $senderEmail) : $senderEmail,
                'subject' => $parts['subject'],
                'html' => $parts['html'] !== '' ? $parts['html'] : null,
                'text' => $parts['text'] !== '' ? $parts['text'] : null,
            ]),
            'recipients' => [[
                'address' => ['email' => $request->recipient],
            ]],
        ];
        if (isset($payload['reply_to']) && is_string($payload['reply_to']) && trim($payload['reply_to']) !== '') {
            $body['content']['reply_to'] = trim($payload['reply_to']);
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/transmissions', [
                'headers' => [
                    'authorization' => (string) $config['api_key'],
                    'content-type' => 'application/json',
                    'accept' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return $this->failureResult('sparkpost_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $decoded = $this->decodeJson($responseBody);
        if ($statusCode < 200 || $statusCode >= 300) {
            return $this->failureResult('sparkpost_http_'.$statusCode, $statusCode >= 500 ? 60 : 0, [
                'status_code' => $statusCode,
                'response' => $decoded ?? substr($responseBody, 0, 500),
            ]);
        }

        $messageId = $this->extractMessageId($decoded, ['results.id', 'id']) ?? $this->fallbackMessageId($request);

        return $this->acceptedResult($messageId, [
            'status_code' => $statusCode,
            'provider_config_code' => $payload['provider_config_code'] ?? null,
        ]);
    }
}
