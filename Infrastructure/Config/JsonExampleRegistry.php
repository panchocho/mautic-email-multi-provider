<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Config;

use JsonException;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Throwable;

final class JsonExampleRegistry
{
    public function __construct(
        private readonly ProviderConfigSchemaRegistry $providerConfigSchemaRegistry,
        private readonly ?SmartMailerSettingsRepository $settingsRepository = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function providerConfig(string $providerType): array
    {
        $providerType = strtolower(trim($providerType));
        $base = $this->providerConfigSchemaRegistry->defaultTemplate($providerType);
        $override = $this->getOverrides()['providers'][$providerType] ?? [];

        return is_array($override) && $override !== []
            ? $this->mergeExample($base, $override)
            : $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileConfig(string $mode): array
    {
        $mode = strtolower(trim($mode));
        $base = match ($mode) {
            RoutingMode::DOMAIN_ROUTING->value,
            RoutingMode::MULTI_DOMAIN_ROUTING->value => [
                'allowed_domains' => ['gmail.com', 'yahoo.com'],
            ],
            RoutingMode::PERCENTAGE_SPLIT->value => [
                'percentage_split' => [
                    'brevo' => 60,
                    'resend' => 40,
                ],
            ],
            RoutingMode::STICKY_CAMPAIGN->value => [
                'campaign_id' => 'campaign-123',
            ],
            RoutingMode::GEO_ROUTING->value => [
                'geo' => 'us',
            ],
            RoutingMode::MX_ROUTING->value => [
                'mx_class' => 'default',
            ],
            RoutingMode::ENGAGEMENT_BASED->value => [
                'engagement_segment' => 'warm',
            ],
            RoutingMode::TAG_BASED->value => [
                'tags' => ['transactional', 'premium'],
            ],
            default => [],
        };
        $override = $this->getOverrides()['profiles'][$mode] ?? [];

        return is_array($override) && $override !== []
            ? $this->mergeExample($base, $override)
            : $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleConstraints(): array
    {
        $base = $this->baseExamples()['rules']['constraints_json'];
        $override = $this->getOverrides()['rules']['constraints_json'] ?? [];

        return is_array($override) && $override !== []
            ? $this->mergeExample($base, $override)
            : $base;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function prettyJsonOrExample(string $json, array $value): string
    {
        $json = trim($json);
        if ($json === '' || $json === '{}' || $json === '[]') {
            return $this->exampleJson($value);
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->exampleJson($value);
        }

        if (!is_array($decoded)) {
            return $this->exampleJson($value);
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $this->exampleJson($value);
    }

    /**
     * @param array<string, mixed> $value
     */
    public function exampleJson(array $value): string
    {
        if ($value === []) {
            return '{}';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array{providers: array<string, array<string, mixed>>, profiles: array<string, array<string, mixed>>, rules: array<string, array<string, mixed>>}
     */
    public function examples(): array
    {
        $base = $this->baseExamples();
        $overrides = $this->getOverrides();
        if ($overrides === []) {
            return $base;
        }

        $providers = $base['providers'];
        foreach ($overrides['providers'] ?? [] as $providerType => $example) {
            if (!is_string($providerType) || !is_array($example)) {
                continue;
            }

            $providerType = strtolower(trim($providerType));
            $providers[$providerType] = isset($providers[$providerType]) && is_array($providers[$providerType])
                ? $this->mergeExample($providers[$providerType], $example)
                : $example;
        }

        $profiles = $base['profiles'];
        foreach ($overrides['profiles'] ?? [] as $mode => $example) {
            if (!is_string($mode) || !is_array($example)) {
                continue;
            }

            $mode = strtolower(trim($mode));
            $profiles[$mode] = isset($profiles[$mode]) && is_array($profiles[$mode])
                ? $this->mergeExample($profiles[$mode], $example)
                : $example;
        }

        $rules = $base['rules'];
        foreach ($overrides['rules'] ?? [] as $key => $example) {
            if (!is_string($key) || !is_array($example)) {
                continue;
            }

            $key = strtolower(trim($key));
            $rules[$key] = isset($rules[$key]) && is_array($rules[$key])
                ? $this->mergeExample($rules[$key], $example)
                : $example;
        }

        return [
            'providers' => $providers,
            'profiles' => $profiles,
            'rules' => $rules,
        ];
    }

    /**
     * @return array{providers: array<string, array<string, mixed>>, profiles: array<string, array<string, mixed>>, rules: array<string, array<string, mixed>>}
     */
    private function baseExamples(): array
    {
        return [
            'providers' => [
                'brevo' => $this->providerConfigSchemaRegistry->defaultTemplate('brevo'),
                'resend' => $this->providerConfigSchemaRegistry->defaultTemplate('resend'),
                'amazon_ses' => $this->providerConfigSchemaRegistry->defaultTemplate('amazon_ses'),
                'sendgrid' => $this->providerConfigSchemaRegistry->defaultTemplate('sendgrid'),
                'mailgun' => $this->providerConfigSchemaRegistry->defaultTemplate('mailgun'),
                'postal' => $this->providerConfigSchemaRegistry->defaultTemplate('postal'),
                'powermta' => $this->providerConfigSchemaRegistry->defaultTemplate('powermta'),
                'smtp_generic' => $this->providerConfigSchemaRegistry->defaultTemplate('smtp_generic'),
                'smtp_only' => $this->providerConfigSchemaRegistry->defaultTemplate('smtp_only'),
                'sparkpost' => $this->providerConfigSchemaRegistry->defaultTemplate('sparkpost'),
            ],
            'profiles' => [
                RoutingMode::DOMAIN_ROUTING->value => ['allowed_domains' => ['gmail.com', 'yahoo.com']],
                RoutingMode::MULTI_DOMAIN_ROUTING->value => ['allowed_domains' => ['gmail.com', 'yahoo.com']],
                RoutingMode::PERCENTAGE_SPLIT->value => ['percentage_split' => ['brevo' => 60, 'resend' => 40]],
                RoutingMode::STICKY_CAMPAIGN->value => ['campaign_id' => 'campaign-123'],
                RoutingMode::GEO_ROUTING->value => ['geo' => 'us'],
                RoutingMode::MX_ROUTING->value => ['mx_class' => 'default'],
                RoutingMode::ENGAGEMENT_BASED->value => ['engagement_segment' => 'warm'],
                RoutingMode::TAG_BASED->value => ['tags' => ['transactional', 'premium']],
            ],
            'rules' => [
                'constraints_json' => [
                    'tenant_id' => 'tenant-a',
                    'campaign_type' => 'transactional',
                    'region' => 'us',
                    'message_type' => 'transactional',
                    'priority_min' => 0,
                    'priority_max' => 100,
                    'recipient_domain' => '*.example.com',
                    'metadata' => [
                        'source' => 'example',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{providers: array<string, array<string, mixed>>, profiles: array<string, array<string, mixed>>, rules: array<string, array<string, mixed>>}
     */
    private function getOverrides(): array
    {
        try {
            $overrides = $this->settingsRepository?->getJson('ui.json_examples', []);
        } catch (Throwable) {
            return [
                'providers' => [],
                'profiles' => [],
                'rules' => [],
            ];
        }
        if (!is_array($overrides) || $overrides === []) {
            return [
                'providers' => [],
                'profiles' => [],
                'rules' => [],
            ];
        }

        return [
            'providers' => is_array($overrides['providers'] ?? null) ? $overrides['providers'] : [],
            'profiles' => is_array($overrides['profiles'] ?? null) ? $overrides['profiles'] : [],
            'rules' => is_array($overrides['rules'] ?? null) ? $overrides['rules'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeExample(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if ($this->isList($value) || $this->isList($base[$key])) {
                    $base[$key] = $value;
                    continue;
                }

                $base[$key] = $this->mergeExample($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
