<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\ProviderDomainBindingRepository;

#[ORM\Entity(repositoryClass: ProviderDomainBindingRepository::class)]
#[ORM\Table(
    name: 'smr_provider_domain_binding',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_smr_provider_domain', columns: ['provider_id', 'domain'])]
)]
class ProviderDomainBinding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'domainBindings')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $domain;

    #[ORM\Column(type: 'smallint')]
    private int $priority = 100;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $domain, int $priority = 100)
    {
        $this->domain = mb_strtolower($domain);
        $this->priority = $priority;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = mb_strtolower($domain);
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
