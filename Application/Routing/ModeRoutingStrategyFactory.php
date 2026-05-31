<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use InvalidArgumentException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class ModeRoutingStrategyFactory
{
    /**
     * @var array<string, ModeRoutingStrategyInterface>
     */
    private array $strategies = [];

    /**
     * @param iterable<ModeRoutingStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->mode()->value] = $strategy;
        }
    }

    public function forMode(RoutingMode $mode): ModeRoutingStrategyInterface
    {
        return $this->strategies[$mode->value]
            ?? throw new InvalidArgumentException(sprintf('Routing mode "%s" is not configured.', $mode->value));
    }
}

