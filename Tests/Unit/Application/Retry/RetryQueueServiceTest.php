<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Retry;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessor;
use MauticPlugin\SmartMailerRouterBundle\Application\Provider\ProviderAdapterFactory;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ModeRoutingEngine;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ModeRoutingStrategyFactory;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ProfileRulesRoutingResolver;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderGuardInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\RetryPolicyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\ProviderConfigurationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RetryQueueServiceTest extends TestCase
{
    public function testProcessDueConsumesAcceptedRetryAndDeletesRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 99,
                    'message_id' => 'retry_req-1',
                    'attempt' => 1,
                    'next_attempt_at' => '2026-05-31 14:00:00',
                    'status' => 'pending',
                    'provider_name' => 'powermta',
                    'provider_code' => 'powermta',
                    'routing_mode' => 'priority_based',
                    'command_json' => json_encode([
                        'request_id' => 'req-1',
                        'tenant_id' => 'tenant-a',
                        'recipient' => 'test@example.com',
                        'message_type' => 'transactional',
                        'region' => 'us',
                        'routing_mode' => 'priority_based',
                        'payload' => ['subject' => 'Retry', 'body' => 'Hello'],
                        'metadata' => ['campaign_type' => 'test', 'priority' => 100],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]);
        $connection->expects(self::once())
            ->method('update')
            ->with('smr_retry_queue', self::arrayHasKey('status'), ['id' => 99]);
        $connection->expects(self::once())
            ->method('delete')
            ->with('smr_retry_queue', ['id' => 99]);

        $retryPolicy = $this->createMock(RetryPolicyInterface::class);
        $retryPolicy->method('shouldRetry')->willReturn(true);
        $retryPolicy->method('nextDelaySeconds')->willReturn(30);

        $adapter = new class () implements ProviderAdapterInterface {
            public function getProfile(): ProviderProfile
            {
                return new ProviderProfile(
                    id: 'powermta',
                    name: 'powermta',
                    type: ProviderType::POWERMTA,
                    enabled: true,
                    weight: 120,
                    priority: 120,
                    throughputLimit: 5000,
                    tags: ['transactional'],
                    supportedMessageTypes: ['transactional'],
                    supportedRegions: ['us'],
                );
            }

            public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult
            {
                return new ProviderSendResult(provider: 'powermta', accepted: true, providerMessageId: 'pmta-123');
            }

            public function ingestDeliveryEvent(array $event): void
            {
            }
        };
        $factory = new ProviderAdapterFactory([$adapter]);

        $routingConnection = $this->createMock(Connection::class);
        $routingConnection->method('fetchAssociative')->willReturnCallback(
            static function (string $sql, array $params = []): array|false {
                if (str_contains($sql, 'FROM smr_routing_profile')) {
                    return [
                        'id' => 1,
                        'name' => 'default',
                        'mode' => 'priority_based',
                        'config' => '{}',
                    ];
                }

                return false;
            }
        );
        $routingConnection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql, array $params = []): array {
                if (str_contains($sql, 'FROM smr_routing_rule')) {
                    return [[
                        'id' => 13,
                        'provider_id' => 'provider-1',
                        'domain_pattern' => '',
                        'priority' => 100,
                        'weight' => 100,
                        'constraints' => '{}',
                        'provider_code' => 'powermta',
                        'provider_type' => 'powermta',
                        'provider_enabled' => 1,
                    ]];
                }

                return [];
            }
        );
        $resolver = new ProfileRulesRoutingResolver($routingConnection, 'default');

        $strategy = new class () implements ModeRoutingStrategyInterface {
            public function mode(): RoutingMode
            {
                return RoutingMode::PRIORITY_BASED;
            }

            public function rank(RoutingRequest $request, \MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext $context): array
            {
                return ['powermta'];
            }
        };
        $guard = new class () implements ProviderGuardInterface {
            public function isEligible(ProviderProfile $profile, RoutingRequest $request): bool
            {
                return true;
            }
        };
        $engine = new ModeRoutingEngine(
            new ModeRoutingStrategyFactory([$strategy]),
            $guard
        );

        $providerConnection = $this->createMock(Connection::class);
        $providerConnection->method('fetchAssociative')->willReturn([
            'id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'powermta',
            'code' => 'powermta',
            'provider_type' => 'powermta',
            'enabled' => 1,
            'weight' => 120,
            'priority' => 120,
            'throughput_limit' => 5000,
            'cost_per_email' => 0.00004,
            'reputation' => 88,
            'health_score' => 90,
            'tags' => '["transactional"]',
            'notes' => null,
            'quarantined_until' => null,
            'config' => '{"sender_email":"sender@example.com"}',
        ]);
        $repo = new ProviderConfigurationRepository($providerConnection);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $message, array $stamps = []): Envelope => new Envelope($message));

        $processor = new RouteEmailProcessor(
            $factory,
            $engine,
            $resolver,
            $repo,
            $entityManager,
            $bus
        );

        $service = new RetryQueueService($connection, $retryPolicy, $processor);
        $result = $service->processDue();

        self::assertSame(1, $result['processed']);
        self::assertSame(0, $result['requeued']);
        self::assertSame(0, $result['expired']);
        self::assertSame(0, $result['invalid']);
    }
}
