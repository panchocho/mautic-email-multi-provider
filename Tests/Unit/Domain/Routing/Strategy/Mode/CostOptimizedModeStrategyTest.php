<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Domain\Routing\Strategy\Mode;

use PHPUnit\Framework\TestCase;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode\CostOptimizedModeStrategy;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class CostOptimizedModeStrategyTest extends TestCase
{
    public function testCheaperProviderGetsHigherRankWhenHealthIsClose(): void
    {
        $strategy = new CostOptimizedModeStrategy();
        $context = new RoutingContext([
            'cheap' => new ProviderProfile('cheap', 'cheap', ProviderType::POSTAL, true, 100, 80, 1000, [], 0.00005, 85, 88),
            'expensive' => new ProviderProfile('expensive', 'expensive', ProviderType::AMAZON_SES, true, 100, 90, 1000, [], 0.00020, 90, 90),
        ]);
        $request = new RoutingRequest('req-2', 'tenant', 'campaign', 'us', 'marketing', 1, 'user@example.com');

        $rank = $strategy->rank($request, $context);

        self::assertSame('cheap', $rank[0]);
    }
}

