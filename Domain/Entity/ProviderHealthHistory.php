<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\ProviderHealthHistoryRepository;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\HealthScore;

#[ORM\Entity(repositoryClass: ProviderHealthHistoryRepository::class)]
#[ORM\Table(
    name: 'smr_provider_health_history',
    indexes: [new ORM\Index(name: 'idx_smr_health_provider_checked', columns: ['provider_id', 'checked_at'])]
)]
class ProviderHealthHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'healthHistory')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Provider $provider;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column(type: 'smallint')]
    private int $score;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $details = [];

    public function __construct(Provider $provider, HealthScore $score, string $status, ?\DateTimeImmutable $checkedAt = null)
    {
        $this->provider = $provider;
        $this->score = $score->toInt();
        $this->status = $status;
        $this->checkedAt = $checkedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function getHealthScore(): HealthScore
    {
        return HealthScore::fromInt($this->score);
    }

    public function setHealthScore(HealthScore $score): void
    {
        $this->score = $score->toInt();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }

    /** @param array<string, mixed> $details */
    public function setDetails(array $details): void
    {
        $this->details = $details;
    }
}
