<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Config\Routing\Strategy;

use PHPUnit\Framework\TestCase;
use MauticPlugin\SmartMailerRouterBundle\Config\Health\HealthScoreCalculator;
use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Strategy\HealthAwareRoutingStrategy;

final class HealthAwareRoutingStrategyTest extends TestCase
{
    public function testSelectsHighestScoringProviderOverThreshold(): void
    {
        $strategy = new HealthAwareRoutingStrategy(new HealthScoreCalculator(), 60);

        $selected = $strategy->selectProvider(
            ['provider_a', 'provider_b'],
            [
                'health' => [
                    'provider_a' => ['success_rate' => 0.96, 'latency_ms' => 300, 'bounce_rate' => 0.02],
                    'provider_b' => ['success_rate' => 0.99, 'latency_ms' => 120, 'bounce_rate' => 0.01],
                ],
            ]
        );

        self::assertSame('provider_b', $selected);
    }

    public function testReturnsNullWhenAllProvidersAreBelowThreshold(): void
    {
        $strategy = new HealthAwareRoutingStrategy(new HealthScoreCalculator(), 90);

        $selected = $strategy->selectProvider(
            ['provider_a', 'provider_b'],
            [
                'health' => [
                    'provider_a' => ['success_rate' => 0.60, 'latency_ms' => 4000, 'bounce_rate' => 0.20],
                    'provider_b' => ['success_rate' => 0.40, 'latency_ms' => 3500, 'bounce_rate' => 0.35],
                ],
            ]
        );

        self::assertNull($selected);
    }
}

