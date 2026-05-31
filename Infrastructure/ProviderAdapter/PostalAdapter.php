<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PostalAdapter extends AbstractProviderAdapter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct(new ProviderProfile(
            id: 'postal',
            name: 'postal',
            type: ProviderType::POSTAL,
            enabled: true,
            weight: 60,
            priority: 80,
            throughputLimit: 2500,
            domains: [],
            costPerEmail: 0.00005,
            reputation: 84.0,
            healthScore: 85.0,
            tags: ['self_hosted', 'bulk'],
            supportedMessageTypes: ['marketing', 'transactional'],
            supportedRegions: ['us', 'eu', 'latam']
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
            return $this->failureResult('postal_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'postal_smtp',
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

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://postal.example'), '/');
        $senderName = $this->resolveSenderName($payload, $config);
        $payloadJson = [
            'from' => $this->formatSenderHeader($senderEmail, $senderName),
            'to' => [$request->recipient],
            'subject' => $parts['subject'],
        ];
        if ($parts['html'] !== '') {
            $payloadJson['html_body'] = $parts['html'];
        }
        if ($parts['text'] !== '') {
            $payloadJson['plain_body'] = $parts['text'];
        }
        if (isset($payload['reply_to']) && is_string($payload['reply_to']) && trim($payload['reply_to']) !== '') {
            $payloadJson['reply_to'] = trim($payload['reply_to']);
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/api/v1/messages/message', [
                'headers' => [
                    'x-server-api-key' => (string) $config['api_key'],
                    'content-type' => 'application/json',
                    'accept' => 'application/json',
                ],
                'json' => $payloadJson,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return $this->failureResult('postal_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $decoded = $this->decodeJson($responseBody);
        if ($statusCode < 200 || $statusCode >= 300) {
            return $this->failureResult('postal_http_'.$statusCode, $statusCode >= 500 ? 60 : 0, [
                'status_code' => $statusCode,
                'response' => $decoded ?? substr($responseBody, 0, 500),
            ]);
        }

        $messageId = $this->extractMessageId($decoded, ['data.message_id', 'message_id']) ?? $this->fallbackMessageId($request);

        return $this->acceptedResult($messageId, [
            'status_code' => $statusCode,
            'provider_config_code' => $payload['provider_config_code'] ?? null,
        ]);
    }

    private function formatSenderHeader(string $email, string $name): string
    {
        return $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
    }
}
