<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\RoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

abstract class AbstractRoutingStrategy implements RoutingStrategyInterface
{
    public function supports(RoutingRequest $request): bool
    {
        return true;
    }

    /**
     * @param array<string, float> $currentScores
     *
     * @return array<string, float>
     */
    public function score(RoutingRequest $request, RoutingContext $context, array $currentScores): array
    {
        foreach ($currentScores as $provider => $score) {
            $currentScores[$provider] = $score + $this->providerDelta($provider, $request, $context);
        }

        return $currentScores;
    }

    protected function providerFloat(RoutingContext $context, string $provider, string $key, float $default = 0.0): float
    {
        $value = $context->stats[$provider][$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return list<string>
     */
    protected function providerList(RoutingContext $context, string $provider, string $key): array
    {
        $value = $context->stats[$provider][$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    abstract protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float;
}

