<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Provider;

use InvalidArgumentException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterFactoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderAdapterInterface;

final class ProviderAdapterFactory implements ProviderAdapterFactoryInterface
{
    /**
     * @var array<string, ProviderAdapterInterface>
     */
    private array $adaptersByName = [];

    /**
     * @param iterable<ProviderAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->adaptersByName[strtolower($adapter->getProfile()->name)] = $adapter;
        }
    }

    public function create(string $provider): ProviderAdapterInterface
    {
        $key = strtolower($provider);
        if (!isset($this->adaptersByName[$key])) {
            throw new InvalidArgumentException(sprintf('Provider adapter "%s" is not registered.', $provider));
        }

        return $this->adaptersByName[$key];
    }

    public function all(): array
    {
        return $this->adaptersByName;
    }
}

