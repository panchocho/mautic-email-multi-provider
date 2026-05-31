<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Retry;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessor;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\RetryPolicyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;

final class RetryQueueService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RetryPolicyInterface $retryPolicy,
        private readonly RouteEmailProcessor $routeEmailProcessor
    ) {
    }

    public function scheduleFailure(
        RouteEmailCommand $command,
        string $providerName,
        ?string $providerCode,
        ProviderSendResult $result
    ): bool {
        $reason = (string) ($result->reason ?? 'temporary_failure');
        $attempt = $this->nextAttemptFor($command->requestId);
        if (!$this->retryPolicy->shouldRetry($attempt, $reason)) {
            return false;
        }

        $delaySeconds = max(
            $result->retryAfterSeconds,
            $this->retryPolicy->nextDelaySeconds($attempt, $reason)
        );
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nextAttemptAt = $now->modify(sprintf('+%d seconds', max(0, $delaySeconds)));

        $payload = [
            'request_id' => $command->requestId,
            'tenant_id' => $command->tenantId,
            'recipient' => $command->recipient,
            'message_type' => $command->messageType,
            'region' => $command->region,
            'routing_mode' => $command->routingMode,
            'payload' => $command->payload,
            'metadata' => $command->metadata,
            'provider_name' => $providerName,
            'provider_code' => $providerCode,
            'reason' => $reason,
        ];

        $messageId = $this->messageIdFor($command->requestId);
        $existing = $this->connection->fetchAssociative(
            'SELECT attempt, created_at FROM smr_retry_queue WHERE message_id = :message_id',
            ['message_id' => $messageId]
        );

        $row = [
            'message_id' => $messageId,
            'attempt' => ($existing !== false && is_array($existing)) ? ((int) ($existing['attempt'] ?? 0) + 1) : 1,
            'next_attempt_at' => $nextAttemptAt->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'provider_name' => $providerName,
            'provider_code' => $providerCode,
            'routing_mode' => $command->routingMode,
            'command_json' => json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}',
            'last_error' => $reason,
            'created_at' => ($existing !== false && is_array($existing) && isset($existing['created_at']))
                ? (string) $existing['created_at']
                : $now->format('Y-m-d H:i:s'),
        ];

        if ($existing !== false && is_array($existing)) {
            $this->connection->update('smr_retry_queue', [
                'attempt' => $row['attempt'],
                'next_attempt_at' => $row['next_attempt_at'],
                'status' => $row['status'],
                'provider_name' => $row['provider_name'],
                'provider_code' => $row['provider_code'],
                'routing_mode' => $row['routing_mode'],
                'command_json' => $row['command_json'],
                'last_error' => $row['last_error'],
            ], ['message_id' => $messageId]);
        } else {
            $this->connection->insert('smr_retry_queue', $row);
        }

        return true;
    }

    /**
     * @return array{processed: int, requeued: int, expired: int, invalid: int}
     */
    public function processDue(int $limit = 100): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, message_id, attempt, next_attempt_at, status, provider_name, provider_code, routing_mode, command_json
             FROM smr_retry_queue
             WHERE status = \'pending\' AND next_attempt_at <= :now
             ORDER BY next_attempt_at ASC
             LIMIT '.$limit,
            ['now' => $now->format('Y-m-d H:i:s')]
        );

        $processed = 0;
        $requeued = 0;
        $expired = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            $retryId = (int) ($row['id'] ?? 0);
            $this->connection->update('smr_retry_queue', [
                'status' => 'processing',
                'last_error' => null,
            ], ['id' => $retryId]);

            $commandData = $this->decodeCommand((string) ($row['command_json'] ?? ''));
            if ($commandData === null) {
                $this->connection->update('smr_retry_queue', [
                    'status' => 'dead',
                    'last_error' => 'invalid_retry_payload',
                ], ['id' => $retryId]);
                ++$invalid;

                continue;
            }

            $retryCommand = new RouteEmailCommand(
                requestId: (string) ($commandData['request_id'] ?? ''),
                tenantId: (string) ($commandData['tenant_id'] ?? ''),
                recipient: (string) ($commandData['recipient'] ?? ''),
                messageType: (string) ($commandData['message_type'] ?? 'transactional'),
                region: (string) ($commandData['region'] ?? 'us'),
                routingMode: (string) ($commandData['routing_mode'] ?? 'round_robin'),
                payload: is_array($commandData['payload'] ?? null) ? $commandData['payload'] : [],
                metadata: is_array($commandData['metadata'] ?? null) ? $commandData['metadata'] : []
            );
            $execution = $this->routeEmailProcessor->process($retryCommand);

            if ($execution->result->accepted) {
                $this->connection->delete('smr_retry_queue', ['id' => $retryId]);
                ++$processed;

                continue;
            }

            $scheduled = $this->scheduleFailure(
                $retryCommand,
                $execution->primaryProvider,
                $execution->providerCode,
                $execution->result
            );

            if ($scheduled) {
                ++$requeued;
                continue;
            }

            $this->connection->delete('smr_retry_queue', ['id' => $retryId]);
            ++$expired;
        }

        return [
            'processed' => $processed,
            'requeued' => $requeued,
            'expired' => $expired,
            'invalid' => $invalid,
        ];
    }

    private function messageIdFor(string $requestId): string
    {
        return 'retry_'.substr(sha1($requestId), 0, 48);
    }

    private function nextAttemptFor(string $requestId): int
    {
        $messageId = $this->messageIdFor($requestId);
        $existing = $this->connection->fetchOne(
            'SELECT attempt FROM smr_retry_queue WHERE message_id = :message_id',
            ['message_id' => $messageId]
        );

        return $existing === false ? 1 : ((int) $existing + 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeCommand(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
