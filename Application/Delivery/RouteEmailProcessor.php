<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Delivery;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\SmartMailerRouterBundle\Application\Provider\ProviderAdapterFactory;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ModeRoutingEngine;
use MauticPlugin\SmartMailerRouterBundle\Application\Routing\ProfileRulesRoutingResolver;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\IngestDeliveryEventCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\ProviderConfigurationRepository;
use Symfony\Component\Messenger\MessageBusInterface;

final class RouteEmailProcessor
{
    public function __construct(
        private readonly ProviderAdapterFactory $providerAdapterFactory,
        private readonly ModeRoutingEngine $routingEngine,
        private readonly ProfileRulesRoutingResolver $profileRulesResolver,
        private readonly ProviderConfigurationRepository $providerConfigurationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function process(RouteEmailCommand $command): RouteEmailExecution
    {
        $providers = [];
        foreach ($this->providerAdapterFactory->all() as $name => $adapter) {
            $providers[$name] = $adapter->getProfile();
        }

        $request = new RoutingRequest(
            requestId: $command->requestId,
            tenantId: $command->tenantId,
            campaignType: (string) ($command->metadata['campaign_type'] ?? 'default'),
            region: $command->region,
            messageType: $command->messageType,
            priority: (int) ($command->metadata['priority'] ?? 0),
            recipient: $command->recipient,
            metadata: $command->metadata
        );
        $mode = RoutingMode::tryFrom($command->routingMode) ?? RoutingMode::ROUND_ROBIN;
        $plan = $this->profileRulesResolver->resolve($request, $mode, array_keys($providers));

        $selectedProviders = [];
        foreach ($plan->providerNames as $providerName) {
            $profile = $providers[$providerName] ?? null;
            if ($profile !== null) {
                $selectedProviders[$providerName] = $profile;
            }
        }
        if ($selectedProviders === []) {
            $selectedProviders = $providers;
        }

        $contextMetadata = $command->metadata;
        if ($plan->profileName !== null && $plan->profileName !== '') {
            $contextMetadata['routing_profile'] = $plan->profileName;
        }
        if ($plan->profileId !== null) {
            $contextMetadata['routing_profile_id'] = $plan->profileId;
        }
        if ($plan->matchedRuleIds !== []) {
            $contextMetadata['matched_rule_ids'] = $plan->matchedRuleIds;
        }
        if ($plan->providerCodesByName !== []) {
            $contextMetadata['provider_codes_by_name'] = $plan->providerCodesByName;
        }

        $context = new RoutingContext($selectedProviders, metadata: $contextMetadata);
        $decision = $this->routingEngine->route($request, $context, $plan->mode);

        $adapter = $this->providerAdapterFactory->create($decision->primaryProvider);
        $payload = $command->payload;
        $selectedProviderCode = $this->firstProviderCodeFor(
            $decision->primaryProvider,
            $plan->providerCodesByName
        );
        if ($selectedProviderCode !== null) {
            $payload['provider_code'] = $selectedProviderCode;
        }

        $providerConfig = $this->providerConfigurationRepository->resolveProvider(
            $decision->primaryProvider,
            $selectedProviderCode
        );
        if ($providerConfig !== null) {
            $payload['provider_config'] = $providerConfig;
            $payload['provider_config_code'] = $providerConfig['code'] ?? null;
        }

        $result = $adapter->queueSend($request, $payload);

        $this->persistDeliveryLog(
            request: $request,
            primaryProvider: $decision->primaryProvider,
            providerCode: $selectedProviderCode,
            routingProfileId: $plan->profileId,
            result: $result,
            payload: $payload,
            rankedProviders: $decision->rankedProviders,
            providerConfig: $providerConfig
        );

        $this->bus->dispatch(new IngestDeliveryEventCommand([
            'request_id' => $command->requestId,
            'provider' => $decision->primaryProvider,
            'provider_code' => $selectedProviderCode,
            'mode' => $mode->value,
            'selection_reason' => $mode->value,
            'routing_profile' => $plan->profileName,
            'routing_profile_id' => $plan->profileId,
            'matched_rule_ids' => $plan->matchedRuleIds,
            'ranked_providers' => $decision->rankedProviders,
            'accepted' => $result->accepted,
            'provider_message_id' => $result->providerMessageId,
            'reason' => $result->reason,
            'retry_scheduled' => false,
            'recipient' => $command->recipient,
            'metadata' => $command->metadata,
        ]));

        return new RouteEmailExecution(
            primaryProvider: $decision->primaryProvider,
            providerCode: $selectedProviderCode,
            result: $result
        );
    }

    /**
     * @param array<string, mixed>|null $providerConfig
     * @param array<string, mixed> $payload
     * @param list<string> $rankedProviders
     */
    private function persistDeliveryLog(
        RoutingRequest $request,
        string $primaryProvider,
        ?string $providerCode,
        ?int $routingProfileId,
        \MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult $result,
        array $payload,
        array $rankedProviders,
        ?array $providerConfig
    ): void {
        if (!is_array($providerConfig) || !isset($providerConfig['id']) || !is_string($providerConfig['id']) || $providerConfig['id'] === '') {
            return;
        }

        $recipientDomain = strtolower(ltrim((string) (strrchr($request->recipient, '@') ?: ''), '@'));
        $now = gmdate('Y-m-d H:i:s');
        $metadata = [
            'request_id' => $request->requestId,
            'routing_mode' => $request->metadata['routing_mode'] ?? null,
            'provider_code' => $providerCode,
            'provider_config_code' => $providerConfig['code'] ?? null,
            'provider_name' => $primaryProvider,
            'ranked_providers' => $rankedProviders,
            'payload_keys' => array_keys($payload),
            'retry_scheduled' => !$result->accepted,
        ];

        $this->entityManager->getConnection()->insert('smr_delivery_log', [
            'provider_id' => $providerConfig['id'],
            'routing_profile_id' => $routingProfileId,
            'external_message_id' => $result->providerMessageId,
            'recipient' => $request->recipient,
            'sender' => (string) ($payload['from_email'] ?? $payload['sender_email'] ?? $providerConfig['config']['sender_email'] ?? ''),
            'subject' => (string) ($payload['subject'] ?? ''),
            'domain' => $recipientDomain !== '' ? $recipientDomain : 'unknown',
            'status' => $result->accepted ? 'sent' : 'failed',
            'attempt_no' => 1,
            'failover_count' => max(0, count($rankedProviders) - 1),
            'selection_reason' => (string) ($request->metadata['selection_reason'] ?? $request->metadata['routing_mode'] ?? 'mode'),
            'http_status' => isset($result->metadata['status_code']) && is_numeric($result->metadata['status_code'])
                ? (int) $result->metadata['status_code']
                : null,
            'latency_ms' => isset($result->metadata['latency_ms']) && is_numeric($result->metadata['latency_ms'])
                ? (int) $result->metadata['latency_ms']
                : null,
            'failure_reason' => $result->accepted ? null : ($result->reason ?? 'unknown'),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
            'attempted_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string, list<string>> $providerCodesByName
     */
    private function firstProviderCodeFor(string $providerName, array $providerCodesByName): ?string
    {
        $codes = $providerCodesByName[strtolower($providerName)] ?? null;
        if (!is_array($codes) || $codes === []) {
            return null;
        }

        $first = trim((string) ($codes[0] ?? ''));

        return $first !== '' ? $first : null;
    }
}
