<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Domain\Routing\Strategy\Mode;

use PHPUnit\Framework\TestCase;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode\RoundRobinModeStrategy;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class RoundRobinModeStrategyTest extends TestCase
{
    public function testRanksAllProviders(): void
    {
        $strategy = new RoundRobinModeStrategy();
        $context = new RoutingContext([
            'amazon_ses' => new ProviderProfile('amazon_ses', 'amazon_ses', ProviderType::AMAZON_SES, true, 100, 100, 1000, [], 0.0001, 90, 90),
            'mailgun' => new ProviderProfile('mailgun', 'mailgun', ProviderType::MAILGUN, true, 100, 100, 1000, [], 0.0001, 90, 90),
            'sendgrid' => new ProviderProfile('sendgrid', 'sendgrid', ProviderType::SENDGRID, true, 100, 100, 1000, [], 0.0001, 90, 90),
        ]);
        $request = new RoutingRequest('req-1', 'tenant', 'campaign', 'us', 'transactional', 1, 'user@example.com');

        $rank = $strategy->rank($request, $context);

        self::assertCount(3, $rank);
        self::assertEqualsCanonicalizing(['amazon_ses', 'mailgun', 'sendgrid'], $rank);
    }
}
