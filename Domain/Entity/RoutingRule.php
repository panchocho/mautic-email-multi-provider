<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\RoutingRuleRepository;

#[ORM\Entity(repositoryClass: RoutingRuleRepository::class)]
#[ORM\Table(
    name: 'smr_routing_rule',
    indexes: [
        new ORM\Index(name: 'idx_smr_rule_profile_priority', columns: ['profile_id', 'priority']),
        new ORM\Index(name: 'idx_smr_rule_provider', columns: ['provider_id']),
    ]
)]
class RoutingRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RoutingProfile::class, inversedBy: 'rules')]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?RoutingProfile $profile = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'routingRules')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Provider $provider = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $domainPattern = null;

    #[ORM\Column(type: 'integer')]
    private int $priority = 100;

    #[ORM\Column(type: 'smallint')]
    private int $weight = 100;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'constraints', type: 'json')]
    private array $constraints = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(int $priority = 100, int $weight = 100)
    {
        $this->priority = $priority;
        $this->weight = $weight;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): ?RoutingProfile
    {
        return $this->profile;
    }

    public function setProfile(RoutingProfile $profile): void
    {
        $this->profile = $profile;
        $this->touch();
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): void
    {
        $this->provider = $provider;
        $this->touch();
    }

    public function getDomainPattern(): ?string
    {
        return $this->domainPattern;
    }

    public function setDomainPattern(?string $domainPattern): void
    {
        $this->domainPattern = $domainPattern;
        $this->touch();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
        $this->touch();
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /** @param array<string, mixed> $constraints */
    public function setConstraints(array $constraints): void
    {
        $this->constraints = $constraints;
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
