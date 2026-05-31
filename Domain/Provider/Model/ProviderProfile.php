<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class ProviderProfile
{
    /**
     * @param list<string> $domains
     * @param list<string> $tags
     * @param list<string> $supportedMessageTypes
     * @param list<string> $supportedRegions
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly string $id = '',
        public readonly string $name = '',
        public readonly ProviderType $type = ProviderType::SMTP_GENERIC,
        public readonly bool $enabled = false,
        public readonly int $weight = 0,
        public readonly int $priority = 0,
        public readonly int $throughputLimit = 0,
        public readonly array $domains = [],
        public readonly float $costPerEmail = 0.0,
        public readonly float $reputation = 0.0,
        public readonly float $healthScore = 0.0,
        public readonly array $tags = [],
        public readonly ?string $notes = null,
        public readonly array $supportedMessageTypes = [],
        public readonly array $supportedRegions = [],
        public readonly array $config = []
    ) {
    }

    public function supportsDomain(string $domain): bool
    {
        if ($this->domains === []) {
            return true;
        }

        $domain = strtolower($domain);
        foreach ($this->domains as $candidate) {
            $normalized = strtolower($candidate);
            if ($normalized === $domain || str_ends_with($domain, '.' . ltrim($normalized, '.'))) {
                return true;
            }
        }

        return false;
    }

    public function getMaxQps(): int
    {
        return $this->throughputLimit;
    }

    public function getBaseCostMicros(): float
    {
        return max(0.0, $this->costPerEmail * 1000000.0);
    }

    public function getBaseLatencyMs(): int
    {
        $value = $this->config['base_latency_ms'] ?? null;
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return 250;
    }

    public function isWarmupEnabled(): bool
    {
        $value = $this->config['warmup_mode'] ?? $this->config['warmup'] ?? null;
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
        }

        return in_array('warmup', $this->tags, true);
    }
}
