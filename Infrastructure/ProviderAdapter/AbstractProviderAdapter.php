<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use JsonException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

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

        try {
            $senderName = $this->resolveSenderName($payload, $config);
            $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
            $messageId = $this->sendRawSmtpMessage(
                host: $host,
                port: $port,
                encryption: $encryption,
                username: (string) $config['username'],
                password: (string) $config['password'],
                fromEmail: $senderEmail,
                fromName: $senderName,
                recipient: $request->recipient,
                subject: $parts['subject'],
                html: $parts['html'],
                text: $parts['text'],
                replyTo: isset($payload['reply_to']) && is_string($payload['reply_to']) ? trim($payload['reply_to']) : null
            );

            return $this->acceptedResult($messageId, array_merge([
                'transport' => $transportLabel,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
            ], $metadata));
        } catch (\Throwable $exception) {
            return $this->failureResult($transportLabel . '_transport_error', 60, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return resource
     */
    private function openSmtpConnection(string $host, int $port, string $encryption)
    {
        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            $scheme . $host . ':' . $port,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new \RuntimeException(sprintf('SMTP connect failed: %d %s', $errno, $errstr));
        }

        stream_set_timeout($socket, 20);

        return $socket;
    }

    /**
     * @param resource $socket
     * @return array{code: string, lines: list<string>}
     */
    private function readSmtpResponse($socket): array
    {
        $lines = [];
        while (($line = fgets($socket, 4096)) !== false) {
            $line = rtrim($line, "\r\n");
            $lines[] = $line;
            if (strlen($line) >= 4 && ctype_digit(substr($line, 0, 3)) && $line[3] === ' ') {
                break;
            }
        }

        if ($lines === []) {
            throw new \RuntimeException('SMTP response is empty');
        }

        return [
            'code' => substr($lines[0], 0, 3),
            'lines' => $lines,
        ];
    }

    /**
     * @param resource $socket
     * @param list<string> $expectedCodes
     * @return array{code: string, lines: list<string>}
     */
    private function sendSmtpCommand($socket, string $command, array $expectedCodes, string $stage): array
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->readSmtpResponse($socket);
        if (!in_array($response['code'], $expectedCodes, true)) {
            throw new \RuntimeException(sprintf(
                '%s failed: %s',
                $stage,
                implode(' | ', $response['lines'])
            ));
        }

        return $response;
    }

    /**
     * @param resource $socket
     */
    private function authenticateSmtpLogin($socket, string $username, string $password): void
    {
        $this->sendSmtpCommand($socket, 'AUTH LOGIN', ['334'], 'auth login');
        $this->sendSmtpCommand($socket, base64_encode($username), ['334'], 'auth username');
        $this->sendSmtpCommand($socket, base64_encode($password), ['235'], 'auth password');
    }

    private function normalizeEol(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    private function dotStuff(string $body): string
    {
        $lines = explode("\n", $this->normalizeEol($body));
        foreach ($lines as &$line) {
            if ($line !== '' && str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);

        return implode("\r\n", $lines);
    }

    private function buildSmtpMessage(
        string $fromEmail,
        string $fromName,
        string $recipient,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo = null
    ): string {
        $headers = [
            'From: ' . ($fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail),
            'To: <' . $recipient . '>',
            'Subject: ' . $subject,
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@mautic.local>',
            'MIME-Version: 1.0',
        ];
        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        if ($html !== '' && $text !== '') {
            $boundary = '=_smr_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = [
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $text,
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $html,
                '--' . $boundary . '--',
                '',
            ];
        } elseif ($html !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = ['', $html, ''];
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = ['', $text !== '' ? $text : '(no content)', ''];
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $this->dotStuff(implode("\r\n", $body));
    }

    private function sendRawSmtpMessage(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $recipient,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo = null
    ): string {
        $socket = $this->openSmtpConnection($host, $port, $encryption);
        try {
            $this->readSmtpResponse($socket);
            $this->sendSmtpCommand($socket, 'EHLO localhost', ['250'], 'ehlo');

            if ($encryption === 'tls') {
                $this->sendSmtpCommand($socket, 'STARTTLS', ['220'], 'starttls');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS negotiation failed');
                }
                $this->sendSmtpCommand($socket, 'EHLO localhost', ['250'], 'post-tls ehlo');
            }

            $this->authenticateSmtpLogin($socket, $username, $password);
            $this->sendSmtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', ['250'], 'mail from');
            $this->sendSmtpCommand($socket, 'RCPT TO:<' . $recipient . '>', ['250', '251'], 'rcpt to');
            $this->sendSmtpCommand($socket, 'DATA', ['354'], 'data');

            $message = $this->buildSmtpMessage(
                fromEmail: $fromEmail,
                fromName: $fromName,
                recipient: $recipient,
                subject: $subject,
                html: $html,
                text: $text,
                replyTo: $replyTo
            );
            fwrite($socket, $message . "\r\n.\r\n");
            $final = $this->readSmtpResponse($socket);
            if (!in_array($final['code'], ['250'], true)) {
                throw new \RuntimeException('send data failed: ' . implode(' | ', $final['lines']));
            }

            $this->sendSmtpCommand($socket, 'QUIT', ['221'], 'quit');

            return $this->fallbackMessageId(new RoutingRequest(
                requestId: 'smtp-' . bin2hex(random_bytes(8)),
                tenantId: '',
                campaignType: '',
                region: '',
                messageType: '',
                priority: 0,
                recipient: $recipient
            ));
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }
}
