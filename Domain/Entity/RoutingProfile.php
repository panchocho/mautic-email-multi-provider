<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\RoutingProfileRepository;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

#[ORM\Entity(repositoryClass: RoutingProfileRepository::class)]
#[ORM\Table(name: 'smr_routing_profile')]
class RoutingProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120, unique: true)]
    private string $name;

    #[ORM\Column(enumType: RoutingMode::class, length: 32)]
    private RoutingMode $mode;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, RoutingRule> */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: RoutingRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rules;

    /** @var Collection<int, DeliveryLog> */
    #[ORM\OneToMany(mappedBy: 'routingProfile', targetEntity: DeliveryLog::class)]
    private Collection $deliveryLogs;

    public function __construct(string $name, RoutingMode $mode = RoutingMode::ROUND_ROBIN)
    {
        $this->name = $name;
        $this->mode = $mode;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->rules = new ArrayCollection();
        $this->deliveryLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getMode(): RoutingMode
    {
        return $this->mode;
    }

    public function setMode(RoutingMode $mode): void
    {
        $this->mode = $mode;
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
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->touch();
    }

    /** @return Collection<int, RoutingRule> */
    public function getRules(): Collection
    {
        return $this->rules;
    }

    public function addRule(RoutingRule $rule): void
    {
        if ($this->rules->contains($rule)) {
            return;
        }

        $this->rules->add($rule);
        $rule->setProfile($this);
    }

    public function removeRule(RoutingRule $rule): void
    {
        $this->rules->removeElement($rule);
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
