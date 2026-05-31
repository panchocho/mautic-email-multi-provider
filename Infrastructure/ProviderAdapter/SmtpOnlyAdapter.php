<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class SmtpOnlyAdapter extends AbstractProviderAdapter
{
    public function __construct()
    {
        parent::__construct(new ProviderProfile(
            id: 'smtp_only',
            name: 'smtp_only',
            type: ProviderType::SMTP_ONLY,
            enabled: true,
            weight: 65,
            priority: 60,
            throughputLimit: 1500,
            domains: [],
            costPerEmail: 0.00007,
            reputation: 80.0,
            healthScore: 78.0,
            tags: ['smtp', 'custom'],
            supportedMessageTypes: ['transactional'],
            supportedRegions: ['us', 'eu', 'latam', 'apac']
        ));
    }

    public function queueSend(RoutingRequest $request, array $payload): ProviderSendResult
    {
        if (($forcedFailure = $payload['force_failure_provider'] ?? null) !== null
            && is_string($forcedFailure)
            && strcasecmp($forcedFailure, $this->getProfile()->name) === 0
        ) {
            return $this->failureResult('forced_failure', 15);
        }

        /** @var array<string, mixed>|null $config */
        $config = isset($payload['provider_config']) && is_array($payload['provider_config'])
            ? $payload['provider_config']
            : null;
        if ($config === null) {
            return $this->failureResult('smtp_only_config_missing', 60);
        }

        $transport = $this->resolveTransportMode($config, 'smtp');
        if ($transport !== 'smtp') {
            return $this->failureResult('smtp_only_transport_invalid', 60);
        }

        return $this->sendSmtpTransport(
            $request,
            $payload,
            $config,
            'smtp_only',
            null,
            ['provider_config_code' => $payload['provider_config_code'] ?? null]
        );
    }
}
