<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Application\Routing;

use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ProfileRulesRoutingResolver;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use PHPUnit\Framework\TestCase;

final class ProfileRulesRoutingResolverTest extends TestCase
{
    public function testResolvesDefaultProfileAndFiltersProvidersByRule(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array|false {
                if (str_contains($sql, 'WHERE name = :name') && ($params['name'] ?? '') === 'default') {
                    return [
                        'id' => 10,
                        'name' => 'default',
                        'mode' => 'priority_based',
                        'config' => '{}',
                    ];
                }

                return false;
            });
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array {
                if (!str_contains($sql, 'FROM smr_routing_rule')) {
                    return [];
                }

                return [
                    [
                        'id' => 1,
                        'provider_id' => 'provider-resend',
                        'provider_code' => 'resend',
                        'provider_type' => 'resend',
                        'provider_enabled' => 1,
                        'domain_pattern' => 'gmail.com',
                        'priority' => 500,
                        'weight' => 100,
                        'constraints' => '{}',
                    ],
                    [
                        'id' => 2,
                        'provider_id' => 'provider-sendgrid',
                        'provider_code' => 'sendgrid',
                        'provider_type' => 'sendgrid',
                        'provider_enabled' => 1,
                        'domain_pattern' => 'yahoo.com',
                        'priority' => 450,
                        'weight' => 100,
                        'constraints' => '{}',
                    ],
                ];
            });

        $resolver = new ProfileRulesRoutingResolver($connection, 'default');
        $request = new RoutingRequest(
            requestId: 'req-1',
            tenantId: 'tenant-a',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 10,
            recipient: 'user@gmail.com',
            metadata: []
        );

        $plan = $resolver->resolve(
            request: $request,
            fallbackMode: RoutingMode::ROUND_ROBIN,
            availableProviders: ['amazon_ses', 'resend', 'sendgrid']
        );

        self::assertSame(RoutingMode::PRIORITY_BASED, $plan->mode);
        self::assertSame(['resend'], $plan->providerNames);
        self::assertSame(10, $plan->profileId);
        self::assertSame('default', $plan->profileName);
        self::assertSame([1], $plan->matchedRuleIds);
        self::assertSame(['resend' => ['resend']], $plan->providerCodesByName);
    }

    public function testFallsBackWhenNoProfileExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $resolver = new ProfileRulesRoutingResolver($connection, 'default');
        $request = new RoutingRequest(
            requestId: 'req-2',
            tenantId: 'tenant-b',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 1,
            recipient: 'user@example.com',
            metadata: []
        );

        $plan = $resolver->resolve(
            request: $request,
            fallbackMode: RoutingMode::ROUND_ROBIN,
            availableProviders: ['amazon_ses', 'resend']
        );

        self::assertSame(RoutingMode::ROUND_ROBIN, $plan->mode);
        self::assertSame(['amazon_ses', 'resend'], $plan->providerNames);
        self::assertNull($plan->profileId);
        self::assertNull($plan->profileName);
        self::assertSame([], $plan->matchedRuleIds);
        self::assertSame([], $plan->providerCodesByName);
    }

    public function testResolvesProviderTypeFromRuleAndKeepsProviderCodePreference(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array|false {
                if (str_contains($sql, 'WHERE name = :name') && ($params['name'] ?? '') === 'default') {
                    return [
                        'id' => 12,
                        'name' => 'default',
                        'mode' => 'domain_routing',
                        'config' => '{}',
                    ];
                }

                return false;
            });
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array {
                if (str_contains($sql, 'FROM smr_provider_domain_binding')) {
                    return [];
                }

                if (!str_contains($sql, 'FROM smr_routing_rule')) {
                    return [];
                }

                return [[
                    'id' => 11,
                    'provider_id' => 'provider-brevo-main',
                    'provider_code' => 'brevo-main',
                    'provider_type' => 'brevo',
                    'provider_enabled' => 1,
                    'domain_pattern' => 'gmail.com',
                    'priority' => 500,
                    'weight' => 100,
                    'constraints' => '{}',
                ]];
            });

        $resolver = new ProfileRulesRoutingResolver($connection, 'default');
        $request = new RoutingRequest(
            requestId: 'req-3',
            tenantId: 'tenant-a',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 10,
            recipient: 'user@gmail.com',
            metadata: []
        );

        $plan = $resolver->resolve(
            request: $request,
            fallbackMode: RoutingMode::ROUND_ROBIN,
            availableProviders: ['brevo', 'sendgrid']
        );

        self::assertSame(RoutingMode::DOMAIN_ROUTING, $plan->mode);
        self::assertSame(['brevo'], $plan->providerNames);
        self::assertSame(['brevo' => ['brevo-main']], $plan->providerCodesByName);
    }

    public function testAppliesDomainBindingsInDomainRoutingMode(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array {
                if (!str_contains($sql, 'FROM smr_provider_domain_binding')) {
                    return [];
                }

                return [
                    [
                        'domain' => 'gmail.com',
                        'priority' => 10,
                        'provider_code' => 'brevo-main',
                        'provider_type' => 'brevo',
                        'enabled' => 1,
                    ],
                    [
                        'domain' => '*.yahoo.com',
                        'priority' => 20,
                        'provider_code' => 'sendgrid-main',
                        'provider_type' => 'sendgrid',
                        'enabled' => 1,
                    ],
                ];
            });

        $resolver = new ProfileRulesRoutingResolver($connection, 'default');
        $request = new RoutingRequest(
            requestId: 'req-4',
            tenantId: 'tenant-a',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 10,
            recipient: 'user@gmail.com',
            metadata: []
        );

        $plan = $resolver->resolve(
            request: $request,
            fallbackMode: RoutingMode::DOMAIN_ROUTING,
            availableProviders: ['brevo', 'sendgrid']
        );

        self::assertSame(RoutingMode::DOMAIN_ROUTING, $plan->mode);
        self::assertSame(['brevo'], $plan->providerNames);
        self::assertSame(['brevo' => ['brevo-main']], $plan->providerCodesByName);
    }

    public function testAppliesProfileConfigToMultiDomainBindings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array|false {
                if (str_contains($sql, 'WHERE name = :name') && ($params['name'] ?? '') === 'default') {
                    return [
                        'id' => 14,
                        'name' => 'default',
                        'mode' => 'multi_domain_routing',
                        'config' => '{"allowed_domains":["gmail.com"]}',
                    ];
                }

                return false;
            });
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params = [], mixed ...$rest): array {
                if (!str_contains($sql, 'FROM smr_provider_domain_binding')) {
                    return [];
                }

                return [[
                    'domain' => 'gmail.com',
                    'priority' => 10,
                    'provider_code' => 'brevo-main',
                    'provider_type' => 'brevo',
                    'enabled' => 1,
                ]];
            });

        $resolver = new ProfileRulesRoutingResolver($connection, 'default');
        $request = new RoutingRequest(
            requestId: 'req-5',
            tenantId: 'tenant-a',
            campaignType: 'marketing',
            region: 'us',
            messageType: 'transactional',
            priority: 10,
            recipient: 'user@gmail.com',
            metadata: []
        );

        $plan = $resolver->resolve(
            request: $request,
            fallbackMode: RoutingMode::ROUND_ROBIN,
            availableProviders: ['brevo', 'sendgrid']
        );

        self::assertSame(RoutingMode::MULTI_DOMAIN_ROUTING, $plan->mode);
        self::assertSame(['brevo'], $plan->providerNames);
        self::assertSame(['brevo' => ['brevo-main']], $plan->providerCodesByName);
    }
}
