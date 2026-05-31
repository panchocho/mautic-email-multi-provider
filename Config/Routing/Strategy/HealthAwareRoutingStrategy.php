<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Config\Health\HealthScoreCalculator;
use MauticPlugin\SmartMailerRouterBundle\Config\Routing\Contract\RoutingStrategyInterface;

final class HealthAwareRoutingStrategy implements RoutingStrategyInterface
{
    public function __construct(
        private readonly HealthScoreCalculator $scoreCalculator,
        private readonly int $minimumScore = 60,
    ) {
    }

    public function getName(): string
    {
        return 'health_aware';
    }

    public function selectProvider(array $providers, array $context = []): ?string
    {
        $healthByProvider = $context['health'] ?? [];
        if (!is_array($healthByProvider)) {
            $healthByProvider = [];
        }

        $bestProvider = null;
        $bestScore = PHP_INT_MIN;

        foreach ($providers as $provider) {
            if (!is_string($provider) || $provider === '') {
                continue;
            }

            $snapshot = $healthByProvider[$provider] ?? [];
            if (!is_array($snapshot)) {
                $snapshot = [];
            }

            $score = $this->scoreCalculator->fromSnapshot($snapshot);
            if ($score < $this->minimumScore) {
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProvider = $provider;
            }
        }

        return $bestProvider;
    }
}

