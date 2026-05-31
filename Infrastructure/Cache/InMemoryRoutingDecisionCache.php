<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Cache;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\RoutingDecisionCacheInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingDecision;

final class InMemoryRoutingDecisionCache implements RoutingDecisionCacheInterface
{
    /**
     * @var array<string, array{decision: RoutingDecision, expires_at: int}>
     */
    private array $cache = [];

    public function get(string $cacheKey): ?RoutingDecision
    {
        if (!isset($this->cache[$cacheKey])) {
            return null;
        }

        $entry = $this->cache[$cacheKey];
        if ($entry['expires_at'] < time()) {
            unset($this->cache[$cacheKey]);

            return null;
        }

        return $entry['decision'];
    }

    public function put(string $cacheKey, RoutingDecision $decision, int $ttlSeconds): void
    {
        $this->cache[$cacheKey] = [
            'decision' => $decision,
            'expires_at' => time() + max(1, $ttlSeconds),
        ];
    }
}

