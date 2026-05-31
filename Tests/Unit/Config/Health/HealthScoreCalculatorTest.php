<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Config\Health;

use PHPUnit\Framework\TestCase;
use MauticPlugin\SmartMailerRouterBundle\Config\Health\HealthScoreCalculator;

final class HealthScoreCalculatorTest extends TestCase
{
    public function testCalculateReturnsScoreBetweenZeroAndHundred(): void
    {
        $calculator = new HealthScoreCalculator();

        $score = $calculator->calculate(0.95, 250.0, 0.02);

        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
    }

    public function testFromSnapshotFallsBackToZeroForMissingValues(): void
    {
        $calculator = new HealthScoreCalculator();

        $score = $calculator->fromSnapshot([]);

        self::assertSame(30, $score);
    }

    public function testBetterSnapshotProducesHigherScore(): void
    {
        $calculator = new HealthScoreCalculator();

        $poor = $calculator->fromSnapshot([
            'success_rate' => 0.50,
            'latency_ms' => 6000.0,
            'bounce_rate' => 0.20,
        ]);
        $good = $calculator->fromSnapshot([
            'success_rate' => 0.99,
            'latency_ms' => 120.0,
            'bounce_rate' => 0.01,
        ]);

        self::assertGreaterThan($poor, $good);
    }
}
