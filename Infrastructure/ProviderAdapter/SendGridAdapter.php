<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendGridAdapter extends AbstractProviderAdapter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct(new ProviderProfile(
            id: 'sendgrid',
            name: 'sendgrid',
            type: ProviderType::SENDGRID,
            enabled: true,
            weight: 90,
            priority: 110,
            throughputLimit: 4500,
            domains: [],
            costPerEmail: 0.00013,
            reputation: 90.0,
            healthScore: 92.0,
            tags: ['bulk', 'transactional'],
            supportedMessageTypes: ['transactional', 'marketing'],
            supportedRegions: ['us', 'eu', 'latam', 'apac']
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
            return $this->failureResult('sendgrid_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'sendgrid_smtp',
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

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.sendgrid.com'), '/');
        $senderName = $this->resolveSenderName($payload, $config);
        $content = [];
        if ($parts['html'] !== '') {
            $content[] = ['type' => 'text/html', 'value' => $parts['html']];
        }
        if ($parts['text'] !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $parts['text']];
        }

        $body = [
            'personalizations' => [[
                'to' => [['email' => $request->recipient]],
                'subject' => $parts['subject'],
            ]],
            'from' => array_filter([
                'email' => $senderEmail,
                'name' => $senderName !== '' ? $senderName : null,
            ]),
            'content' => $content,
        ];
        if (isset($payload['reply_to']) && is_string($payload['reply_to']) && trim($payload['reply_to']) !== '') {
            $body['reply_to'] = ['email' => trim($payload['reply_to'])];
        }
        if (isset($payload['metadata']) && is_array($payload['metadata']) && $payload['metadata'] !== []) {
            $body['custom_args'] = array_map(static function (mixed $value): string {
                if (is_scalar($value)) {
                    return (string) $value;
                }

                $encoded = json_encode($value);

                return is_string($encoded) ? $encoded : '';
            }, $payload['metadata']);
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/v3/mail/send', [
                'headers' => [
                    'authorization' => 'Bearer '.(string) $config['api_key'],
                    'content-type' => 'application/json',
                    'accept' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $headers = array_change_key_case($response->getHeaders(false), CASE_LOWER);
            $responseBody = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return $this->failureResult('sendgrid_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return $this->failureResult('sendgrid_http_'.$statusCode, $statusCode >= 500 ? 60 : 0, [
                'status_code' => $statusCode,
                'response' => $this->decodeJson($responseBody) ?? substr($responseBody, 0, 500),
            ]);
        }

        $messageId = null;
        if (isset($headers['x-message-id'][0]) && is_string($headers['x-message-id'][0]) && trim($headers['x-message-id'][0]) !== '') {
            $messageId = trim($headers['x-message-id'][0]);
        }

        return $this->acceptedResult($messageId ?? $this->fallbackMessageId($request), [
            'status_code' => $statusCode,
            'provider_config_code' => $payload['provider_config_code'] ?? null,
        ]);
    }
}
