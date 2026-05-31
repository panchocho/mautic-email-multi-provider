<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Registry;

use InvalidArgumentException;
use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract\RoutingStrategyInterface;

final class RoutingStrategyRegistry
{
    /** @var array<string, RoutingStrategyInterface> */
    private array $strategies = [];

    /**
     * @param iterable<RoutingStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $name = $strategy->getName();
            if (isset($this->strategies[$name])) {
                throw new InvalidArgumentException(sprintf('Duplicate routing strategy "%s".', $name));
            }

            $this->strategies[$name] = $strategy;
        }
    }

    public function get(string $name): RoutingStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new InvalidArgumentException(sprintf('Routing strategy "%s" was not found.', $name));
        }

        return $this->strategies[$name];
    }

    /**
     * @return array<string, RoutingStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}

