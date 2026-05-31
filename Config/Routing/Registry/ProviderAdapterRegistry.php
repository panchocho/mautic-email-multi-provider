<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Registry;

use InvalidArgumentException;
use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract\ProviderAdapterInterface;

final class ProviderAdapterRegistry
{
    /** @var array<string, ProviderAdapterInterface> */
    private array $adapters = [];

    /**
     * @param iterable<ProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $name = $adapter->getName();
            if (isset($this->adapters[$name])) {
                throw new InvalidArgumentException(sprintf('Duplicate provider adapter "%s".', $name));
            }

            $this->adapters[$name] = $adapter;
        }
    }

    public function get(string $name): ProviderAdapterInterface
    {
        if (!isset($this->adapters[$name])) {
            throw new InvalidArgumentException(sprintf('Provider adapter "%s" was not found.', $name));
        }

        return $this->adapters[$name];
    }

    /**
     * @return array<string, ProviderAdapterInterface>
     */
    public function all(): array
    {
        return $this->adapters;
    }
}

