<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\WarmupScheduleRepository;

#[ORM\Entity(repositoryClass: WarmupScheduleRepository::class)]
#[ORM\Table(
    name: 'smr_warmup_schedule',
    indexes: [new ORM\Index(name: 'idx_smr_warmup_schedule_scope_active', columns: ['scope_type', 'scope_key', 'active'])]
)]
class WarmupSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'warmupSchedules')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $scopeType = 'provider';

    #[ORM\Column(type: 'string', length: 255)]
    private string $scopeKey;

    #[ORM\Column(type: 'integer')]
    private int $dayOffset = 1;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $hourUtc = null;

    #[ORM\Column(type: 'integer')]
    private int $targetVolume;

    #[ORM\Column(type: 'integer')]
    private int $incrementStep;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $scopeType, string $scopeKey, int $dayOffset, int $targetVolume, int $incrementStep, ?Provider $provider = null)
    {
        $this->provider = $provider;
        $this->scopeType = $scopeType;
        $this->scopeKey = $scopeKey;
        $this->dayOffset = $dayOffset;
        $this->targetVolume = $targetVolume;
        $this->incrementStep = $incrementStep;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): void
    {
        $this->dayOfWeek = $dayOfWeek;
        $this->touch();
    }

    public function getHourUtc(): ?int
    {
        return $this->hourUtc;
    }

    public function setHourUtc(?int $hourUtc): void
    {
        $this->hourUtc = $hourUtc;
        $this->touch();
    }

    public function getTargetVolume(): int
    {
        return $this->targetVolume;
    }

    public function setTargetVolume(int $targetVolume): void
    {
        $this->targetVolume = $targetVolume;
        $this->touch();
    }

    public function getIncrementStep(): int
    {
        return $this->incrementStep;
    }

    public function setIncrementStep(int $incrementStep): void
    {
        $this->incrementStep = $incrementStep;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
