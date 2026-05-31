<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Cache;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderHealthSnapshot;

final class InMemoryProviderHealthRepository implements ProviderHealthRepositoryInterface
{
    /**
     * @var array<string, ProviderHealthSnapshot>
     */
    private array $items = [];

    /**
     * @param iterable<ProviderHealthSnapshot> $seed
     */
    public function __construct(iterable $seed = [])
    {
        foreach ($seed as $snapshot) {
            $this->items[strtolower($snapshot->provider)] = $snapshot;
        }
    }

    public function get(string $provider): ?ProviderHealthSnapshot
    {
        return $this->items[strtolower($provider)] ?? null;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function put(ProviderHealthSnapshot $snapshot): void
    {
        $this->items[strtolower($snapshot->provider)] = $snapshot;
    }
}

