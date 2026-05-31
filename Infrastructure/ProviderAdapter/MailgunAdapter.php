<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MailgunAdapter extends AbstractProviderAdapter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct(new ProviderProfile(
            id: 'mailgun',
            name: 'mailgun',
            type: ProviderType::MAILGUN,
            enabled: true,
            weight: 95,
            priority: 105,
            throughputLimit: 4000,
            domains: [],
            costPerEmail: 0.00012,
            reputation: 91.0,
            healthScore: 93.0,
            tags: ['transactional', 'api'],
            supportedMessageTypes: ['transactional', 'marketing'],
            supportedRegions: ['us', 'eu']
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
            return $this->failureResult('mailgun_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'mailgun_smtp',
                null,
                ['provider_config_code' => $payload['provider_config_code'] ?? null]
            );
        }

        $required = $this->validateConfig($config, ['api_key', 'domain', 'sender_email']);
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

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.mailgun.net'), '/');
        $domain = trim((string) $config['domain']);
        $form = [
            'from' => $this->formatSenderHeader($senderEmail, $this->resolveSenderName($payload, $config)),
            'to' => $request->recipient,
            'subject' => $parts['subject'],
        ];
        if ($parts['html'] !== '') {
            $form['html'] = $parts['html'];
        }
        if ($parts['text'] !== '') {
            $form['text'] = $parts['text'];
        }
        if (isset($payload['reply_to']) && is_string($payload['reply_to']) && trim($payload['reply_to']) !== '') {
            $form['h:Reply-To'] = trim($payload['reply_to']);
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/v3/'.$domain.'/messages', [
                'auth_basic' => 'api:'.(string) $config['api_key'],
                'body' => $form,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return $this->failureResult('mailgun_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $decoded = $this->decodeJson($responseBody);
        if ($statusCode < 200 || $statusCode >= 300) {
            return $this->failureResult('mailgun_http_'.$statusCode, $statusCode >= 500 ? 60 : 0, [
                'status_code' => $statusCode,
                'response' => $decoded ?? substr($responseBody, 0, 500),
            ]);
        }

        $messageId = $this->extractMessageId($decoded, ['id']) ?? $this->fallbackMessageId($request);

        return $this->acceptedResult($messageId, [
            'status_code' => $statusCode,
            'domain' => $domain,
            'provider_config_code' => $payload['provider_config_code'] ?? null,
        ]);
    }

    private function formatSenderHeader(string $email, string $name): string
    {
        return $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
    }
}
