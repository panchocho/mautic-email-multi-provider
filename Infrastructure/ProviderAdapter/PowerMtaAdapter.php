<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderAdapter;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class PowerMtaAdapter extends AbstractProviderAdapter
{
    public function __construct()
    {
        parent::__construct(new ProviderProfile(
            id: 'powermta',
            name: 'powermta',
            type: ProviderType::POWERMTA,
            enabled: true,
            weight: 85,
            priority: 120,
            throughputLimit: 12000,
            domains: [],
            costPerEmail: 0.00004,
            reputation: 88.0,
            healthScore: 90.0,
            tags: ['bulk', 'dedicated-ip'],
            supportedMessageTypes: ['marketing', 'transactional'],
            supportedRegions: ['us', 'eu']
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
            return $this->failureResult('powermta_config_missing', 60);
        }

        $required = $this->validateConfig($config, ['host', 'port', 'username', 'password', 'sender_email']);
        if ($required !== null) {
            return $this->failureResult($required, 60);
        }

        return $this->sendSmtpTransport(
            $request,
            $payload,
            $config,
            'powermta',
            null,
            ['provider_config_code' => $payload['provider_config_code'] ?? null]
        );
    }
}
