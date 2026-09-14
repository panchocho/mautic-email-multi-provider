<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use Doctrine\DBAL\Connection;
use Throwable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class ProfileRulesRoutingResolver
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%smart_mailer_router.default_profile%')]
        private readonly string $defaultProfileName = 'default'
    ) {
    }

    /**
     * @param list<string> $availableProviders
     */
    public function resolve(RoutingRequest $request, RoutingMode $fallbackMode, array $availableProviders): RoutingPlan
    {
        $normalizedProviders = [];
        foreach ($availableProviders as $provider) {
            $normalized = strtolower(trim($provider));
            if ($normalized !== '') {
                $normalizedProviders[$normalized] = true;
            }
        }

        if ($normalizedProviders === []) {
            return new RoutingPlan($fallbackMode, []);
        }

        try {
            $profile = $this->resolveProfile($request);
            if ($profile === null) {
                $plan = new RoutingPlan($fallbackMode, array_keys($normalizedProviders));

                return $this->applyDomainBindings($plan, $request);
            }

            $mode = RoutingMode::tryFrom((string) ($profile['mode'] ?? '')) ?? $fallbackMode;
            $profileId = (int) ($profile['id'] ?? 0);
            $profileName = (string) ($profile['name'] ?? '');
            $effectiveRequest = $this->applyProfileConfig($request, $profile);

            if ($profileId < 1) {
                $plan = new RoutingPlan($mode, array_keys($normalizedProviders));

                return $this->applyDomainBindings($plan, $effectiveRequest);
            }

            $rules = $this->connection->fetchAllAssociative(
                'SELECT r.id, r.provider_id, r.domain_pattern, r.priority, r.weight, r.constraints,
                        p.code AS provider_code, p.provider_type AS provider_type, p.enabled AS provider_enabled
                 FROM smr_routing_rule r
                 LEFT JOIN smr_provider p ON p.id = r.provider_id
                 WHERE r.profile_id = :profile_id AND r.enabled = 1
                 ORDER BY r.priority DESC, r.weight DESC, r.id ASC',
                ['profile_id' => $profileId]
            );

            if ($rules === []) {
                $plan = new RoutingPlan($mode, array_keys($normalizedProviders), $profileId, $profileName);

                return $this->applyDomainBindings($plan, $effectiveRequest);
            }

            $recipientDomain = $this->extractRecipientDomain($effectiveRequest->recipient);
            $providerNames = [];
            $providerCodesByName = [];
            $matchedRuleIds = [];

            foreach ($rules as $rule) {
                $providerType = strtolower(trim((string) ($rule['provider_type'] ?? '')));
                if ($providerType === '' || !isset($normalizedProviders[$providerType])) {
                    continue;
                }

                if ((int) ($rule['provider_enabled'] ?? 0) !== 1) {
                    continue;
                }

                if (!$this->matchesDomainPattern((string) ($rule['domain_pattern'] ?? ''), $recipientDomain)) {
                    continue;
                }

                $constraints = $this->decodeJsonObject((string) ($rule['constraints'] ?? ''));
                if (!$this->matchesConstraints($constraints, $effectiveRequest, $recipientDomain)) {
                    continue;
                }

                $providerNames[$providerType] = true;
                $ruleId = (int) ($rule['id'] ?? 0);
                if ($ruleId > 0 && !in_array($ruleId, $matchedRuleIds, true)) {
                    $matchedRuleIds[] = $ruleId;
                }

                $providerCode = strtolower(trim((string) ($rule['provider_code'] ?? '')));
                if ($providerCode !== '') {
                    $providerCodesByName[$providerType] ??= [];
                    $this->appendUniqueString($providerCodesByName[$providerType], $providerCode);
                }
            }

            if ($providerNames === []) {
                $plan = new RoutingPlan($mode, array_keys($normalizedProviders), $profileId, $profileName);

                return $this->applyDomainBindings($plan, $effectiveRequest);
            }

            $plan = new RoutingPlan(
                $mode,
                array_keys($providerNames),
                $profileId,
                $profileName,
                $matchedRuleIds,
                $providerCodesByName
            );

            return $this->applyDomainBindings($plan, $effectiveRequest);
        } catch (Throwable) {
            return new RoutingPlan($fallbackMode, array_keys($normalizedProviders));
        }
    }

    private function applyDomainBindings(RoutingPlan $plan, RoutingRequest $request): RoutingPlan
    {
        if ($plan->providerNames === []) {
            return $plan;
        }


        $domains = $this->domainsForBindingLookup($request, $plan->mode);
        if ($domains === []) {
            return $plan;
        }

        $allowedProviders = array_fill_keys($plan->providerNames, true);

        /** @var list<array<string, mixed>> $bindings */
        $bindings = $this->connection->fetchAllAssociative(
            'SELECT b.domain, b.priority, p.code AS provider_code, p.provider_type, p.enabled
             FROM smr_provider_domain_binding b
             INNER JOIN smr_provider p ON p.id = b.provider_id
             WHERE b.active = 1 AND p.enabled = 1
             ORDER BY b.priority ASC, b.id ASC'
        );

        if ($bindings === []) {
            return $plan;
        }

        $matchedProviders = [];
        $bindingCodesByName = [];

        foreach ($bindings as $binding) {
            $providerType = strtolower(trim((string) ($binding['provider_type'] ?? '')));
            if ($providerType === '' || !isset($allowedProviders[$providerType])) {
                continue;
            }

            $pattern = (string) ($binding['domain'] ?? '');
            foreach ($domains as $domain) {
                if (!$this->matchesDomainPattern($pattern, $domain)) {
                    continue;
                }

                $matchedProviders[$providerType] = true;
                $providerCode = strtolower(trim((string) ($binding['provider_code'] ?? '')));
                if ($providerCode !== '') {
                    $bindingCodesByName[$providerType] ??= [];
                    $this->appendUniqueString($bindingCodesByName[$providerType], $providerCode);
                }
                break;
            }
        }

        if ($matchedProviders === []) {
            if (trim((string) ($request->metadata['sender_domain'] ?? '')) !== '') {
                return new RoutingPlan($plan->mode, [], $plan->profileId, $plan->profileName, $plan->matchedRuleIds, $plan->providerCodesByName);
            }

            return $plan;
        }

        $filteredProviders = [];
        foreach ($plan->providerNames as $providerName) {
            if (isset($matchedProviders[$providerName])) {
                $filteredProviders[] = $providerName;
            }
        }

        if ($filteredProviders === []) {
            return $plan;
        }

        $mergedCodesByName = [];
        foreach ($filteredProviders as $providerName) {
            $codes = [];

            foreach ($bindingCodesByName[$providerName] ?? [] as $code) {
                $this->appendUniqueString($codes, $code);
            }
            foreach ($plan->providerCodesByName[$providerName] ?? [] as $code) {
                $this->appendUniqueString($codes, $code);
            }

            if ($codes !== []) {
                $mergedCodesByName[$providerName] = $codes;
            }
        }

        return new RoutingPlan(
            mode: $plan->mode,
            providerNames: $filteredProviders,
            profileId: $plan->profileId,
            profileName: $plan->profileName,
            matchedRuleIds: $plan->matchedRuleIds,
            providerCodesByName: $mergedCodesByName
        );
    }

    private function domainsForBindingLookup(RoutingRequest $request, RoutingMode $mode): array
    {
        $senderDomain = strtolower(trim((string) ($request->metadata['sender_domain'] ?? '')));
        if ($senderDomain !== '') {
            // A Mautic message must only use providers explicitly bound to its sender domain.
            return [$senderDomain];
        }

        if ($mode !== RoutingMode::DOMAIN_ROUTING && $mode !== RoutingMode::MULTI_DOMAIN_ROUTING) {
            return [];
        }

        $domains = [];
        $recipientDomain = $this->extractRecipientDomain($request->recipient);
        if ($recipientDomain !== '') {
            $domains[$recipientDomain] = true;
        }

        if ($mode === RoutingMode::MULTI_DOMAIN_ROUTING) {
            foreach ((array) ($request->metadata['allowed_domains'] ?? []) as $domain) {
                if (is_scalar($domain) && trim((string) $domain) !== '') {
                    $domains[strtolower(trim((string) $domain))] = true;
                }
            }
        }

        return array_keys($domains);
    }
    /**
     * @param list<string> $values
     */
    private function appendUniqueString(array &$values, string $candidate): void
    {
        if ($candidate === '') {
            return;
        }

        if (!in_array($candidate, $values, true)) {
            $values[] = $candidate;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProfile(RoutingRequest $request): ?array
    {
        $metadata = $request->metadata;

        $profileId = filter_var($metadata['routing_profile_id'] ?? null, FILTER_VALIDATE_INT);
        if ($profileId !== false && $profileId > 0) {
            $profile = $this->connection->fetchAssociative(
                'SELECT id, name, mode, config FROM smr_routing_profile WHERE id = :id AND enabled = 1',
                ['id' => $profileId]
            );
            if (is_array($profile)) {
                return $profile;
            }
        }

        $profileName = trim((string) ($metadata['routing_profile'] ?? $metadata['profile'] ?? $metadata['profile_name'] ?? ''));
        if ($profileName !== '') {
            $profile = $this->connection->fetchAssociative(
                'SELECT id, name, mode, config FROM smr_routing_profile WHERE name = :name AND enabled = 1',
                ['name' => $profileName]
            );
            if (is_array($profile)) {
                return $profile;
            }
        }

        $default = trim($this->defaultProfileName);
        if ($default === '') {
            return null;
        }

        $profile = $this->connection->fetchAssociative(
            'SELECT id, name, mode, config FROM smr_routing_profile WHERE name = :name AND enabled = 1',
            ['name' => $default]
        );

        return is_array($profile) ? $profile : null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function applyProfileConfig(RoutingRequest $request, array $profile): RoutingRequest
    {
        $profileConfig = $this->decodeJsonObject((string) ($profile['config'] ?? ''));
        if ($profileConfig === []) {
            return $request;
        }

        $metadata = array_merge($profileConfig, $request->metadata);

        return new RoutingRequest(
            requestId: $request->requestId,
            tenantId: $request->tenantId,
            campaignType: $request->campaignType,
            region: $request->region,
            messageType: $request->messageType,
            priority: $request->priority,
            recipient: $request->recipient,
            metadata: $metadata,
            occurredAt: $request->occurredAt
        );
    }

    /**
     * @param array<string, mixed> $constraints
     */
    private function matchesConstraints(array $constraints, RoutingRequest $request, string $recipientDomain): bool
    {
        if ($constraints === []) {
            return true;
        }

        if (!$this->matchesStringConstraint($constraints['tenant_id'] ?? null, $request->tenantId)) {
            return false;
        }

        if (!$this->matchesStringConstraint($constraints['campaign_type'] ?? null, $request->campaignType)) {
            return false;
        }

        if (!$this->matchesStringConstraint($constraints['region'] ?? null, $request->region)) {
            return false;
        }

        if (!$this->matchesStringConstraint($constraints['message_type'] ?? null, $request->messageType)) {
            return false;
        }

        $priorityMin = $constraints['priority_min'] ?? null;
        if (is_numeric($priorityMin) && $request->priority < (int) $priorityMin) {
            return false;
        }

        $priorityMax = $constraints['priority_max'] ?? null;
        if (is_numeric($priorityMax) && $request->priority > (int) $priorityMax) {
            return false;
        }

        $domainConstraint = $constraints['recipient_domain'] ?? null;
        if (!$this->matchesStringConstraint($domainConstraint, $recipientDomain, true)) {
            return false;
        }

        $metadataConstraints = $constraints['metadata'] ?? null;
        if (is_array($metadataConstraints)) {
            foreach ($metadataConstraints as $key => $expected) {
                $actual = $request->metadata[(string) $key] ?? null;
                if (is_array($expected)) {
                    if (!in_array($actual, $expected, true)) {
                        return false;
                    }
                    continue;
                }

                if ($actual !== $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    private function matchesStringConstraint(mixed $expected, string $actual, bool $allowPattern = false): bool
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        if (is_array($expected)) {
            foreach ($expected as $candidate) {
                if ($this->matchesStringConstraint($candidate, $actual, $allowPattern)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_scalar($expected)) {
            return false;
        }

        $value = strtolower(trim((string) $expected));
        $target = strtolower(trim($actual));

        if ($value === '') {
            return true;
        }

        if ($allowPattern) {
            return $this->matchesDomainPattern($value, $target);
        }

        return $value === $target;
    }

    private function matchesDomainPattern(string $pattern, string $domain): bool
    {
        $pattern = strtolower(trim($pattern));
        $domain = strtolower(trim($domain));

        if ($pattern === '') {
            return true;
        }

        if ($domain === '') {
            return false;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';

            return preg_match($regex, $domain) === 1;
        }

        return $domain === $pattern || str_ends_with($domain, '.' . ltrim($pattern, '.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function extractRecipientDomain(string $email): string
    {
        $candidate = strtolower(trim($email));
        if ($candidate === '' || !str_contains($candidate, '@')) {
            return '';
        }

        $domain = substr($candidate, (int) strrpos($candidate, '@') + 1);

        return trim((string) $domain);
    }
}
