<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Config\Routing\Strategy;

use PHPUnit\Framework\TestCase;
use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Strategy\RoundRobinRoutingStrategy;

final class RoundRobinRoutingStrategyTest extends TestCase
{
    public function testReturnsNullWhenProvidersAreEmpty(): void
    {
        $strategy = new RoundRobinRoutingStrategy();

        self::assertNull($strategy->selectProvider([]));
    }

    public function testCyclesThroughProvidersInRoundRobinOrder(): void
    {
        $strategy = new RoundRobinRoutingStrategy();
        $providers = ['smtp_a', 'smtp_b', 'smtp_c'];

        self::assertSame('smtp_a', $strategy->selectProvider($providers));
        self::assertSame('smtp_b', $strategy->selectProvider($providers));
        self::assertSame('smtp_c', $strategy->selectProvider($providers));
        self::assertSame('smtp_a', $strategy->selectProvider($providers));
    }
}

