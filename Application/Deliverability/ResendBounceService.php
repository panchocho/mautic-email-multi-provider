<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Deliverability;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\DoNotContact as DncEntity;
use Mautic\LeadBundle\Model\DoNotContact as DoNotContactModel;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\ProviderConfigurationRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ResendBounceService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DoNotContactModel $doNotContact,
        private readonly ProviderConfigurationRepository $providerConfiguration,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Process a Resend email.bounced webhook payload.
     *
     * @return array{processed:int, matched:int, skipped:int}
     */
    public function processWebhook(array $payload): array
    {
        $type = strtolower((string) ($payload['type'] ?? ''));
        if (!in_array($type, ['email.bounced', 'email.suppressed'], true)) {
            return ['processed' => 0, 'matched' => 0, 'skipped' => 0];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $recipients = $data['to'] ?? [];
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }
        if (!is_array($recipients)) {
            return ['processed' => 0, 'matched' => 0, 'skipped' => 0];
        }

        $reason = trim((string) ($data['bounce']['message'] ?? $data['suppressed']['message'] ?? $data['reason'] ?? ''));
        if ($reason === '') {
            $reason = $type === 'email.suppressed' ? 'Resend reported a suppressed email.' : 'Resend reported an email bounce.';
        }
        $messageId = trim((string) ($data['email_id'] ?? ''));
        $comments = 'Resend bounce'.($messageId !== '' ? ' ['.$messageId.']' : '').': '.$reason;

        $result = ['processed' => 0, 'matched' => 0, 'skipped' => 0];
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) $recipient));
            if (preg_match('/<([^>]+)>/', $email, $matches)) {
                $email = strtolower(trim($matches[1]));
            }
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result['skipped']++;
                continue;
            }

            $result['processed']++;
            $leadIds = $this->connection->fetchFirstColumn(
                'SELECT id FROM leads WHERE LOWER(email) = :email',
                ['email' => $email]
            );
            if ($leadIds === []) {
                $result['skipped']++;
                continue;
            }

            foreach ($leadIds as $leadId) {
                $this->doNotContact->addDncForContact(
                    (int) $leadId,
                    'email',
                    DncEntity::BOUNCED,
                    $comments,
                    true,
                    true,
                    false,
                );
                $result['matched']++;
            }
        }

        return $result;
    }

    public function verifyWebhookSignature(string $rawBody, string $svixId, string $svixTimestamp, string $svixSignature): bool
    {
        $provider = $this->providerConfiguration->resolveProvider('resend');
        $config = is_array($provider['config'] ?? null) ? $provider['config'] : [];
        $secret = trim((string) ($config['webhook_signing_secret'] ?? ''));
        if ($secret === '' || $svixId === '' || $svixTimestamp === '' || $svixSignature === '') {
            return false;
        }
        if (abs(time() - (int) $svixTimestamp) > 300) {
            return false;
        }

        $secret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $decodedSecret = base64_decode($secret, true);
        $key = $decodedSecret === false ? $secret : $decodedSecret;
        $expected = base64_encode(hash_hmac('sha256', $svixId.'.'.$svixTimestamp.'.'.$rawBody, $key, true));
        foreach (preg_split('/\s+/', trim($svixSignature)) ?: [] as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{webhook_id:string, endpoint:string} */
    public function registerWebhook(string $endpoint): array
    {
        $provider = $this->providerConfiguration->resolveProvider('resend');
        $config = is_array($provider['config'] ?? null) ? $provider['config'] : [];
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Resend API key is not configured.');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.resend.com'), '/');
        $response = $this->httpClient->request('POST', $baseUrl.'/webhooks', [
            'headers' => ['Authorization' => 'Bearer '.$apiKey, 'Content-Type' => 'application/json'],
            'json' => ['endpoint' => $endpoint, 'events' => ['email.bounced', 'email.suppressed']],
            'timeout' => 20,
        ]);
        $body = $response->toArray(false);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || !is_string($body['id'] ?? null)) {
            throw new \RuntimeException('Resend webhook registration failed.');
        }

        $config['webhook_id'] = $body['id'];
        $config['webhook_endpoint'] = $endpoint;
        if (is_string($body['signing_secret'] ?? null) && $body['signing_secret'] !== '') {
            $config['webhook_signing_secret'] = $body['signing_secret'];
        }
        $this->connection->update('smr_provider', ['config' => json_encode($config, JSON_UNESCAPED_SLASHES)], ['id' => $provider['id']]);

        return ['webhook_id' => $body['id'], 'endpoint' => $endpoint];
    }

    /**
     * Poll Resend's email detail endpoint for message IDs stored by the router.
     * Resend does not expose a general historical event listing API, so this is
     * intentionally limited to known message IDs.
     *
     * @return array{checked:int, bounced:int, matched:int, errors:int}
     */
    public function syncKnownDeliveries(int $limit = 500): array
    {
        $provider = $this->providerConfiguration->resolveProvider('resend');
        $config = is_array($provider['config'] ?? null) ? $provider['config'] : [];
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['checked' => 0, 'bounced' => 0, 'matched' => 0, 'errors' => 1];
        }

        $recipientRows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(email) FROM leads WHERE email IS NOT NULL AND email <> ""'
        );
        $recipients = array_fill_keys(array_map('strtolower', array_map('strval', $recipientRows)), true);
        $maxEmails = max(1, min($limit, 2000));
        $pageSize = min(100, $maxEmails);
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.resend.com'), '/');
        $result = ['checked' => 0, 'bounced' => 0, 'matched' => 0, 'errors' => 0];
        $after = null;

        while ($result['checked'] < $maxEmails) {
            $query = ['limit' => min($pageSize, $maxEmails - $result['checked'])];
            if ($after !== null) {
                $query['after'] = $after;
            }

            try {
                $response = $this->httpClient->request('GET', $baseUrl.'/emails', [
                    'headers' => ['Authorization' => 'Bearer '.$apiKey, 'Accept' => 'application/json'],
                    'query' => $query,
                    'timeout' => 20,
                ]);
                $statusCode = $response->getStatusCode();
                $body = $response->toArray(false);
                if ($statusCode < 200 || $statusCode >= 300 || !is_array($body['data'] ?? null)) {
                    $result['errors']++;
                    break;
                }
            } catch (\Throwable) {
                $result['errors']++;
                break;
            }

            $emails = $body['data'];
            if ($emails === []) {
                break;
            }

            foreach ($emails as $email) {
                if (!is_array($email)) {
                    continue;
                }
                $result['checked']++;
                $status = strtolower((string) ($email['last_event'] ?? $email['status'] ?? ''));
                if (!in_array($status, ['bounced', 'complained', 'suppressed'], true)) {
                    continue;
                }

                $matchedRecipients = [];
                foreach ((array) ($email['to'] ?? []) as $recipient) {
                    $normalized = strtolower(trim((string) $recipient));
                    if (preg_match('/<([^>]+)>/', $normalized, $matches)) {
                        $normalized = strtolower(trim($matches[1]));
                    }
                    if (isset($recipients[$normalized])) {
                        $matchedRecipients[] = $normalized;
                    }
                }
                if ($matchedRecipients === []) {
                    continue;
                }

                $result['bounced']++;
                $processed = $this->processWebhook([
                    'type' => $status === 'suppressed' ? 'email.suppressed' : 'email.bounced',
                    'data' => [
                        'email_id' => (string) ($email['id'] ?? ''),
                        'to' => array_values(array_unique($matchedRecipients)),
                        'reason' => 'Resend API status: '.$status,
                    ],
                ]);
                $result['matched'] += $processed['matched'];
            }

            if (($body['has_more'] ?? false) !== true || count($emails) < $pageSize) {
                break;
            }
            $last = end($emails);
            $after = is_array($last) ? (string) ($last['id'] ?? '') : '';
            if ($after === '') {
                break;
            }
        }

        return $result;
    }
}
