<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class AmazonSesAdapter extends AbstractProviderAdapter
{
    public function __construct()
    {
        parent::__construct(new ProviderProfile(
            id: 'amazon_ses',
            name: 'amazon_ses',
            type: ProviderType::AMAZON_SES,
            enabled: true,
            weight: 100,
            priority: 100,
            throughputLimit: 5000,
            domains: [],
            costPerEmail: 0.00010,
            reputation: 92.0,
            healthScore: 95.0,
            tags: ['transactional', 'warmup'],
            supportedMessageTypes: ['transactional', 'marketing'],
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
            return $this->failureResult('amazon_ses_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'api');
        if ($transport === 'smtp') {
            $region = strtolower((string) ($config['region'] ?? ''));
            $defaultHost = $region !== '' ? sprintf('email-smtp.%s.amazonaws.com', $region) : null;
            return $this->sendSmtpTransport(
                $request,
                $payload,
                $config,
                'ses_smtp',
                $defaultHost,
                [
                    'region' => $region !== '' ? $region : null,
                    'provider_config_code' => $payload['provider_config_code'] ?? null,
                ]
            );
        }

        $required = $this->validateConfig($config, ['access_key_id', 'secret_access_key', 'region', 'sender_email']);
        if ($required !== null) {
            return $this->failureResult($required, 60);
        }

        $senderEmail = $this->resolveSenderEmail($payload, $config);
        if ($senderEmail === '') {
            return $this->failureResult('sender_not_configured', 0);
        }

        $senderName = $this->resolveSenderName($payload, $config);
        $parts = $this->resolveMessageParts($payload);
        if ($parts['html'] === '' && $parts['text'] === '') {
            return $this->failureResult('empty_message_body', 0);
        }

        $region = strtolower((string) $config['region']);
        $smtpUsername = (string) $config['access_key_id'];
        $smtpPassword = $this->generateSesSmtpPassword((string) $config['secret_access_key'], $region);
        $port = (int) ($config['port'] ?? 587);
        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        $host = sprintf('email-smtp.%s.amazonaws.com', $region);

        try {
            $transport = \Symfony\Component\Mailer\Transport\Transport::fromDsn(sprintf(
                'smtp://%s:%s@%s:%d?encryption=%s',
                rawurlencode($smtpUsername),
                rawurlencode($smtpPassword),
                $host,
                $port,
                rawurlencode($encryption)
            ));
            $email = new \Symfony\Component\Mime\Email();
            $from = $senderName !== '' ? new \Symfony\Component\Mime\Address($senderEmail, $senderName) : new \Symfony\Component\Mime\Address($senderEmail);
            $email->from($from);
            $email->to($request->recipient);
            $email->subject($parts['subject']);
            if ($parts['html'] !== '') {
                $email->html($parts['html']);
            }
            if ($parts['text'] !== '') {
                $email->text($parts['text']);
            }

            $sentMessage = $transport->send($email);
            $messageId = $sentMessage->getMessageId() ?: $this->fallbackMessageId($request);

            return $this->acceptedResult($messageId, [
                'region' => $region,
                'transport' => 'ses_smtp',
                'provider_config_code' => $payload['provider_config_code'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return $this->failureResult('amazon_ses_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function generateSesSmtpPassword(string $secretAccessKey, string $region): string
    {
        $date = '11111111';
        $service = 'ses';
        $terminal = 'aws4_request';
        $message = 'SendRawEmail';
        $version = chr(0x04);

        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kTerminal = hash_hmac('sha256', $terminal, $kService, true);
        $kMessage = hash_hmac('sha256', $message, $kTerminal, true);

        return base64_encode($version . $kMessage);
    }
}
