<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\WarmupStateRepository;

#[ORM\Entity(repositoryClass: WarmupStateRepository::class)]
#[ORM\Table(
    name: 'smr_warmup_state',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_smr_warmup_state_scope', columns: ['scope_type', 'scope_key'])]
)]
class WarmupState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'warmupStates')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $scopeType = 'provider';

    #[ORM\Column(type: 'string', length: 255)]
    private string $scopeKey;

    #[ORM\Column(type: 'integer')]
    private int $currentDay = 1;

    #[ORM\Column(type: 'integer')]
    private int $sentToday = 0;

    #[ORM\Column(type: 'integer')]
    private int $currentDailyLimit = 0;

    #[ORM\Column(type: 'integer')]
    private int $currentHourlyLimit = 0;

    #[ORM\Column(type: 'smallint')]
    private int $consecutiveHealthyDays = 0;

    #[ORM\Column(type: 'smallint')]
    private int $consecutiveUnhealthyDays = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAdvancedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextPlannedAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $notes = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $scopeType, string $scopeKey, ?Provider $provider = null)
    {
        $this->provider = $provider;
        $this->scopeType = $scopeType;
        $this->scopeKey = $scopeKey;
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

    public function getCurrentDailyLimit(): int
    {
        return $this->currentDailyLimit;
    }

    public function setCurrentDailyLimit(int $currentDailyLimit): void
    {
        $this->currentDailyLimit = $currentDailyLimit;
        $this->touch();
    }

    public function getCurrentHourlyLimit(): int
    {
        return $this->currentHourlyLimit;
    }

    public function setCurrentHourlyLimit(int $currentHourlyLimit): void
    {
        $this->currentHourlyLimit = $currentHourlyLimit;
        $this->touch();
    }

    public function getConsecutiveHealthyDays(): int
    {
        return $this->consecutiveHealthyDays;
    }

    public function setConsecutiveHealthyDays(int $consecutiveHealthyDays): void
    {
        $this->consecutiveHealthyDays = $consecutiveHealthyDays;
        $this->touch();
    }

    public function getConsecutiveUnhealthyDays(): int
    {
        return $this->consecutiveUnhealthyDays;
    }

    public function setConsecutiveUnhealthyDays(int $consecutiveUnhealthyDays): void
    {
        $this->consecutiveUnhealthyDays = $consecutiveUnhealthyDays;
        $this->touch();
    }

    public function getLastAdvancedAt(): ?\DateTimeImmutable
    {
        return $this->lastAdvancedAt;
    }

    public function setLastAdvancedAt(?\DateTimeImmutable $lastAdvancedAt): void
    {
        $this->lastAdvancedAt = $lastAdvancedAt;
        $this->touch();
    }

    public function getNextPlannedAt(): ?\DateTimeImmutable
    {
        return $this->nextPlannedAt;
    }

    public function setNextPlannedAt(?\DateTimeImmutable $nextPlannedAt): void
    {
        $this->nextPlannedAt = $nextPlannedAt;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /** @param array<string, mixed> $notes */
    public function setNotes(array $notes): void
    {
        $this->notes = $notes;
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
