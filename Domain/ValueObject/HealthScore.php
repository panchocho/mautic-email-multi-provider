<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject;

final readonly class HealthScore
{
    public function __construct(private int $value = 0)
    {
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException('HealthScore must be between 0 and 100.');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function isHealthy(int $threshold = 70): bool
    {
        return $this->value >= $threshold;
    }
}
