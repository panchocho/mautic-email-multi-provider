<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderId;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'smr_provider')]
#[ORM\Index(name: 'idx_smr_provider_enabled_priority', columns: ['enabled', 'priority'])]
#[ORM\Index(name: 'idx_smr_provider_type_enabled', columns: ['provider_type', 'enabled'])]
class Provider
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 128)]
    private string $name;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $code;

    #[ORM\Column(enumType: ProviderType::class, length: 32)]
    private ProviderType $providerType;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'integer')]
    private int $weight = 100;

    #[ORM\Column(type: 'integer')]
    private int $priority = 100;

    #[ORM\Column(type: 'integer')]
    private int $throughputLimit = 1000;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 6)]
    private string $costPerEmail = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $reputation = '100.00';

    #[ORM\Column(type: 'smallint')]
    private int $healthScore = 100;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $quarantinedUntil = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProviderDomainBinding> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: ProviderDomainBinding::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $domainBindings;

    /** @var Collection<int, ProviderMetricBucket> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: ProviderMetricBucket::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $metricBuckets;

    /** @var Collection<int, ProviderHealthHistory> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: ProviderHealthHistory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $healthHistory;

    /** @var Collection<int, WarmupSchedule> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: WarmupSchedule::class)]
    private Collection $warmupSchedules;

    /** @var Collection<int, WarmupState> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: WarmupState::class)]
    private Collection $warmupStates;

    /** @var Collection<int, DeliveryLog> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: DeliveryLog::class)]
    private Collection $deliveryLogs;

    public function __construct(
        string $name,
        string $code,
        ProviderType $providerType,
        ?ProviderId $id = null,
    ) {
        $this->id = ($id ?? ProviderId::generate())->toString();
        if (!Uuid::isValid($this->id)) {
            throw new \InvalidArgumentException('Provider id must be a valid UUID.');
        }

        $this->name = $name;
        $this->code = $code;
        $this->providerType = $providerType;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->domainBindings = new ArrayCollection();
        $this->metricBuckets = new ArrayCollection();
        $this->healthHistory = new ArrayCollection();
        $this->warmupSchedules = new ArrayCollection();
        $this->warmupStates = new ArrayCollection();
        $this->deliveryLogs = new ArrayCollection();
    }

    public function getRawId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Provider name cannot be empty.');
        }

        $this->name = $trimmed;
        $this->touch();
    }

    public function getId(): ProviderId
    {
        return ProviderId::fromString($this->id);
    }

    public function getProviderType(): ProviderType
    {
        return $this->providerType;
    }

    public function setProviderType(ProviderType $providerType): void
    {
        $this->providerType = $providerType;
        $this->touch();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '' || preg_match('/^[a-z0-9_-]{2,64}$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Provider code must match /^[a-z0-9_-]{2,64}$/');
        }

        $this->code = $normalized;
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

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = max(1, $weight);
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

    public function getThroughputLimit(): int
    {
        return $this->throughputLimit;
    }

    public function setThroughputLimit(int $throughputLimit): void
    {
        $this->throughputLimit = max(1, $throughputLimit);
        $this->touch();
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = array_values(array_unique(array_map('strval', $tags)));
        $this->touch();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
        $this->touch();
    }

    public function getHealthScore(): int
    {
        return $this->healthScore;
    }

    public function setHealthScore(int $healthScore): void
    {
        $this->healthScore = max(0, min(100, $healthScore));
        $this->touch();
    }

    public function getReputation(): float
    {
        return (float) $this->reputation;
    }

    public function setReputation(float $reputation): void
    {
        $value = max(0.0, min(100.0, $reputation));
        $this->reputation = number_format($value, 2, '.', '');
        $this->touch();
    }

    public function getCostPerEmail(): float
    {
        return (float) $this->costPerEmail;
    }

    public function setCostPerEmail(float $costPerEmail): void
    {
        $this->costPerEmail = number_format(max(0.0, $costPerEmail), 6, '.', '');
        $this->touch();
    }

    public function isQuarantined(DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        return $this->quarantinedUntil !== null && $this->quarantinedUntil > $now;
    }

    public function quarantineUntil(?DateTimeImmutable $until): void
    {
        $this->quarantinedUntil = $until;
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

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
