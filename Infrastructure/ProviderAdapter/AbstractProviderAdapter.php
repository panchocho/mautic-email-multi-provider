<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use JsonException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use Symfony\Component\Mailer\Transport\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

abstract class AbstractProviderAdapter implements ProviderAdapterInterface
{
    public function __construct(private readonly ProviderProfile $profile)
    {
    }

    public function getProfile(): ProviderProfile
    {
        return $this->profile;
    }

    public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult
    {
        $forcedFailure = $payload['force_failure_provider'] ?? null;
        if (is_string($forcedFailure) && strcasecmp($forcedFailure, $this->profile->name) === 0) {
            return new ProviderSendResult(
                provider: $this->profile->name,
                accepted: false,
                reason: 'forced_failure',
                retryAfterSeconds: 15
            );
        }

        $messageId = strtolower($this->profile->name) . '-' . md5($request->requestId . $request->recipient);

        return new ProviderSendResult(
            provider: $this->profile->name,
            accepted: true,
            providerMessageId: $messageId,
            metadata: [
                'region' => $request->region,
                'message_type' => $request->messageType,
            ]
        );
    }

    public function ingestDeliveryEvent(array $event): void
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveTransportMode(array $config, string $defaultTransport = 'api'): string
    {
        $explicitTransport = isset($config['transport']) ? strtolower(trim((string) $config['transport'])) : '';
        if ($explicitTransport === 'api' || $explicitTransport === 'smtp') {
            return $explicitTransport;
        }

        $hasApiCredentials = $this->hasApiCredentials($config);
        $hasSmtpCredentials = $this->hasSmtpCredentials($config);

        if ($hasApiCredentials && !$hasSmtpCredentials) {
            return 'api';
        }

        if ($hasSmtpCredentials && !$hasApiCredentials) {
            return 'smtp';
        }

        return in_array($defaultTransport, ['api', 'smtp'], true) ? $defaultTransport : 'api';
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $requiredKeys
     */
    protected function validateConfig(array $config, array $requiredKeys): ?string
    {
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $config)) {
                return $requiredKey . '_missing';
            }

            $value = $config[$requiredKey];
            if (is_string($value) && trim($value) === '') {
                return $requiredKey . '_missing';
            }

            if ($value === null) {
                return $requiredKey . '_missing';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function hasApiCredentials(array $config): bool
    {
        foreach (['api_key', 'access_key_id', 'secret_access_key'] as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function hasSmtpCredentials(array $config): bool
    {
        foreach (['host', 'port', 'username', 'password'] as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject: string, html: string, text: string}
     */
    protected function resolveMessageParts(array $payload): array
    {
        $subject = trim((string) ($payload['subject'] ?? ''));
        if ($subject === '') {
            $subject = '(no subject)';
        }

        $html = (string) ($payload['html'] ?? $payload['html_content'] ?? '');
        $text = (string) ($payload['text'] ?? $payload['text_content'] ?? '');
        if ($html === '' && $text === '') {
            $text = (string) ($payload['body'] ?? '');
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function resolveSenderEmail(array $payload, array $config): string
    {
        $senderEmail = (string) ($payload['from_email'] ?? $config['sender_email'] ?? $config['from_email'] ?? '');

        return trim($senderEmail);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function resolveSenderName(array $payload, array $config): string
    {
        $senderName = (string) ($payload['from_name'] ?? $config['sender_name'] ?? $config['from_name'] ?? '');

        return trim($senderName);
    }

    /**
     * @param array<string, mixed>|null $decoded
     * @param list<string> $paths
     */
    protected function extractMessageId(?array $decoded, array $paths): ?string
    {
        if ($decoded === null) {
            return null;
        }

        foreach ($paths as $path) {
            $value = $this->arrayPath($decoded, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function arrayPath(array $decoded, string $path): mixed
    {
        $current = $decoded;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    protected function decodeJson(string $body): ?array
    {
        if (trim($body) === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function fallbackMessageId(RoutingRequest $request): string
    {
        return strtolower($this->profile->name) . '-' . md5($request->requestId . $request->recipient);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    protected function acceptedResult(?string $messageId, array $metadata = []): ProviderSendResult
    {
        return new ProviderSendResult(
            provider: $this->profile->name,
            accepted: true,
            providerMessageId: $messageId,
            metadata: $metadata
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    protected function failureResult(string $reason, int $retryAfterSeconds = 0, array $metadata = []): ProviderSendResult
    {
        return new ProviderSendResult(
            provider: $this->profile->name,
            accepted: false,
            reason: $reason,
            retryAfterSeconds: $retryAfterSeconds,
            metadata: $metadata
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    protected function sendSmtpTransport(
        RoutingRequest $request,
        array $payload,
        array $config,
        string $transportLabel = 'smtp',
        ?string $defaultHost = null,
        array $metadata = []
    ): ProviderSendResult {
        $required = $this->validateConfig($config, ['username', 'password', 'sender_email']);
        if ($required !== null) {
            if ($required === 'username_missing' || $required === 'password_missing') {
                return $this->failureResult('smtp_' . $required, 60);
            }

            return $this->failureResult($required, 60);
        }

        $host = trim((string) ($config['host'] ?? $defaultHost ?? ''));
        if ($host === '') {
            return $this->failureResult('smtp_host_missing', 60);
        }

        $port = (int) ($config['port'] ?? 587);
        if ($port < 1 || $port > 65535) {
            return $this->failureResult('smtp_port_invalid', 60);
        }

        $senderEmail = $this->resolveSenderEmail($payload, $config);
        if ($senderEmail === '') {
            return $this->failureResult('sender_not_configured', 0);
        }

        $parts = $this->resolveMessageParts($payload);
        if ($parts['html'] === '' && $parts['text'] === '') {
            return $this->failureResult('empty_message_body', 0);
        }

        $username = (string) $config['username'];
        $password = (string) $config['password'];
        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        $dsn = sprintf(
            'smtp://%s:%s@%s:%d?encryption=%s',
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port,
            rawurlencode($encryption)
        );

        try {
            $transport = Transport::fromDsn($dsn);
            $email = new Email();
            $senderName = $this->resolveSenderName($payload, $config);
            $email->from($senderName !== '' ? new Address($senderEmail, $senderName) : new Address($senderEmail));
            $email->to($request->recipient);
            $email->subject($parts['subject']);
            if ($parts['html'] !== '') {
                $email->html($parts['html']);
            }
            if ($parts['text'] !== '') {
                $email->text($parts['text']);
            }
            if (isset($payload['reply_to']) && is_string($payload['reply_to']) && trim($payload['reply_to']) !== '') {
                $email->replyTo(trim($payload['reply_to']));
            }

            $sentMessage = $transport->send($email);
            $messageId = $sentMessage->getMessageId() ?: $this->fallbackMessageId($request);

            return $this->acceptedResult($messageId, array_merge([
                'transport' => $transportLabel,
                'host' => $host,
                'port' => $port,
            ], $metadata));
        } catch (\Throwable $exception) {
            return $this->failureResult($transportLabel . '_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
