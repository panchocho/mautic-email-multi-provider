<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Health;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Health\AutoActionsService;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Cache\InMemoryProviderHealthRepository;
use PHPUnit\Framework\TestCase;

final class AutoActionsServiceTest extends TestCase
{
    public function testAppliesPauseActionAndPersistsHistory(): void
    {
        $repository = new InMemoryProviderHealthRepository([
            new ProviderHealthSnapshot(
                provider: 'sendgrid',
                score: 20.0,
                successCount: 8,
                failureCount: 12,
                bounceRate: 0.25,
                complaintRate: 0.02,
                p95LatencyMs: 1200,
                updatedAt: new DateTimeImmutable()
            ),
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['id' => '11111111-1111-1111-1111-111111111111']);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'smr_provider',
                self::callback(static function (array $data): bool {
                    return ($data['enabled'] ?? null) === 0
                        && isset($data['quarantined_until'])
                        && isset($data['health_score']);
                }),
                ['id' => '11111111-1111-1111-1111-111111111111']
            );
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                'smr_provider_health_history',
                self::callback(static function (array $data): bool {
                    return ($data['status'] ?? null) === 'quarantined'
                        && ($data['message'] ?? null) !== null;
                })
            );

        $service = new AutoActionsService($repository, $connection);
        $actions = $service->apply('sendgrid');

        self::assertSame('pause_provider', $actions[0]['action']);
        self::assertLessThan(20.0, $repository->get('sendgrid')->score);
    }
}
