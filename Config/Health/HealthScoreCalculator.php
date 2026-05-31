<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Health;

final class HealthScoreCalculator
{
    public function __construct(
        private readonly float $successRateWeight = 0.7,
        private readonly float $latencyWeight = 0.2,
        private readonly float $bounceWeight = 0.1,
    ) {
    }

    /**
     * @param array{success_rate?: float, latency_ms?: float, bounce_rate?: float} $snapshot
     */
    public function fromSnapshot(array $snapshot): int
    {
        return $this->calculate(
            (float) ($snapshot['success_rate'] ?? 0.0),
            (float) ($snapshot['latency_ms'] ?? 0.0),
            (float) ($snapshot['bounce_rate'] ?? 0.0),
        );
    }

    public function calculate(float $successRate, float $latencyMs, float $bounceRate): int
    {
        $successRate = $this->clamp($successRate, 0.0, 1.0);
        $bounceRate = $this->clamp($bounceRate, 0.0, 1.0);
        $latencyRatio = $this->scale($latencyMs, 0.0, 10_000.0);
        $latencyScore = 1.0 - $latencyRatio;

        $weightSum = $this->successRateWeight + $this->latencyWeight + $this->bounceWeight;
        if ($weightSum <= 0.0) {
            return 0;
        }

        $score =
            ($successRate * $this->successRateWeight) +
            ($latencyScore * $this->latencyWeight) +
            ((1.0 - $bounceRate) * $this->bounceWeight);

        return (int) round($this->clamp($score / $weightSum, 0.0, 1.0) * 100);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function scale(float $value, float $min, float $max): float
    {
        $clamped = $this->clamp($value, $min, $max);
        $range = $max - $min;
        if ($range <= 0.0) {
            return 0.0;
        }

        return ($clamped - $min) / $range;
    }
}
