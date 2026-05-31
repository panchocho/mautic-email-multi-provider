<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\DeliveryLogRepository;

#[ORM\Entity(repositoryClass: DeliveryLogRepository::class)]
#[ORM\Table(
    name: 'smr_delivery_log',
    indexes: [
        new ORM\Index(name: 'idx_smr_delivery_provider_attempted', columns: ['provider_id', 'attempted_at']),
        new ORM\Index(name: 'idx_smr_delivery_status_attempted', columns: ['status', 'attempted_at']),
        new ORM\Index(name: 'idx_smr_delivery_domain', columns: ['domain']),
        new ORM\Index(name: 'idx_smr_delivery_external_id', columns: ['external_message_id']),
        new ORM\Index(name: 'idx_smr_delivery_profile', columns: ['routing_profile_id']),
    ]
)]
class DeliveryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'deliveryLogs')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Provider $provider;

    #[ORM\ManyToOne(targetEntity: RoutingProfile::class, inversedBy: 'deliveryLogs')]
    #[ORM\JoinColumn(name: 'routing_profile_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RoutingProfile $routingProfile = null;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $externalMessageId = null;

    #[ORM\Column(type: 'string', length: 320)]
    private string $recipient;

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $sender = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $domain;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status;

    #[ORM\Column(type: 'smallint')]
    private int $attemptNo = 1;

    #[ORM\Column(type: 'smallint')]
    private int $failoverCount = 0;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $selectionReason = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $latencyMs = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $attemptedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Provider $provider, string $recipient, string $domain, string $status, ?\DateTimeImmutable $attemptedAt = null)
    {
        $this->provider = $provider;
        $this->recipient = $recipient;
        $this->domain = mb_strtolower($domain);
        $this->status = $status;
        $this->attemptedAt = $attemptedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getRoutingProfile(): ?RoutingProfile
    {
        return $this->routingProfile;
    }

    public function setRoutingProfile(?RoutingProfile $routingProfile): void
    {
        $this->routingProfile = $routingProfile;
    }

    public function getExternalMessageId(): ?string
    {
        return $this->externalMessageId;
    }

    public function setExternalMessageId(?string $externalMessageId): void
    {
        $this->externalMessageId = $externalMessageId;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(?string $sender): void
    {
        $this->sender = $sender;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = mb_strtolower($domain);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $httpStatus): void
    {
        $this->httpStatus = $httpStatus;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(?int $latencyMs): void
    {
        $this->latencyMs = $latencyMs;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): void
    {
        $this->failureReason = $failureReason;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getAttemptedAt(): \DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
