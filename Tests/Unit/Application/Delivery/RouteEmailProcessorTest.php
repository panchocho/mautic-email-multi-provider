<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Delivery;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessor;
use MauticPlugin\SmartMailerRouterBundle\Application\Provider\ProviderAdapterFactory;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ModeRoutingEngine;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ModeRoutingStrategyFactory;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ProfileRulesRoutingResolver;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderGuardInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\IngestDeliveryEventCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\ProviderConfigurationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RouteEmailProcessorTest extends TestCase
{
    public function testProcessPersistsDeliveryLogAndDispatchesIngestEvent(): void
    {
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

            public function queueSend(\MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest $request, array $payload): ProviderSendResult
            {
                return new ProviderSendResult(
                    provider: 'powermta',
                    accepted: true,
                    providerMessageId: 'pmta-123'
                );
            }

            public function ingestDeliveryEvent(array $event): void
            {
            }
        };
        $factory = new ProviderAdapterFactory([$adapter]);

        $routingConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $routingConnection->method('fetchAssociative')->willReturnCallback(
            static function (string $sql, array $params = []): array|false {
                if (str_contains($sql, 'FROM smr_routing_profile')) {
                    return [
                        'id' => 7,
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

            public function rank(\MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest $request, \MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext $context): array
            {
                return ['powermta'];
            }
        };
        $guard = new class () implements ProviderGuardInterface {
            public function isEligible(ProviderProfile $profile, \MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest $request): bool
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

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                'smr_delivery_log',
                self::callback(static function (array $data): bool {
                    return ($data['provider_id'] ?? null) === '22222222-2222-2222-2222-222222222222'
                        && ($data['recipient'] ?? null) === 'test@example.com'
                        && ($data['status'] ?? null) === 'sent'
                        && ($data['subject'] ?? null) === 'Hello';
                })
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $message): bool {
                return $message instanceof IngestDeliveryEventCommand
                    && ($message->event['provider'] ?? null) === 'powermta'
                    && ($message->event['provider_code'] ?? null) === 'powermta';
            }))
            ->willReturnCallback(static fn (object $message, array $stamps = []): Envelope => new Envelope($message));

        $processor = new RouteEmailProcessor(
            $factory,
            $engine,
            $resolver,
            $repo,
            $entityManager,
            $bus
        );

        $execution = $processor->process(new RouteEmailCommand(
            requestId: 'req-1',
            tenantId: 'tenant-a',
            recipient: 'test@example.com',
            messageType: 'transactional',
            region: 'us',
            routingMode: RoutingMode::PRIORITY_BASED->value,
            payload: ['subject' => 'Hello', 'body' => 'World'],
            metadata: ['campaign_type' => 'test', 'priority' => 100]
        ));

        self::assertSame('powermta', $execution->primaryProvider);
        self::assertSame('powermta', $execution->providerCode);
        self::assertTrue($execution->result->accepted);
        self::assertSame('pmta-123', $execution->result->providerMessageId);
    }
}
