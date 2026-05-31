<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ProviderId
{
    private string $value;

    public function __construct(string $value = '')
    {
        if ($value === '') {
            $this->value = Uuid::v7()->toRfc4122();

            return;
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
