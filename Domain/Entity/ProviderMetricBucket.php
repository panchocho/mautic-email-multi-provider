<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository\ProviderMetricBucketRepository;

#[ORM\Entity(repositoryClass: ProviderMetricBucketRepository::class)]
#[ORM\Table(
    name: 'smr_provider_metric_bucket',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_smr_metric_bucket', columns: ['provider_id', 'bucket_start', 'bucket_interval_minutes'])]
)]
class ProviderMetricBucket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'metricBuckets')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Provider $provider;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $bucketStart;

    #[ORM\Column(type: 'smallint')]
    private int $bucketIntervalMinutes;

    #[ORM\Column(type: 'integer')]
    private int $sentCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $deliveredCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $bouncedCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $complaintCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $openCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $clickCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $deferralCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $failureCount = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $avgLatencyMs = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $inboxPlacementEstimate = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Provider $provider, \DateTimeImmutable $bucketStart, int $bucketIntervalMinutes)
    {
        $this->provider = $provider;
        $this->bucketStart = $bucketStart;
        $this->bucketIntervalMinutes = $bucketIntervalMinutes;
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

    public function getBucketStart(): \DateTimeImmutable
    {
        return $this->bucketStart;
    }

    public function getBucketIntervalMinutes(): int
    {
        return $this->bucketIntervalMinutes;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): void
    {
        $this->sentCount = $sentCount;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function setDeliveredCount(int $deliveredCount): void
    {
        $this->deliveredCount = $deliveredCount;
    }

    public function getBouncedCount(): int
    {
        return $this->bouncedCount;
    }

    public function setBouncedCount(int $bouncedCount): void
    {
        $this->bouncedCount = $bouncedCount;
    }

    public function getComplaintCount(): int
    {
        return $this->complaintCount;
    }

    public function setComplaintCount(int $complaintCount): void
    {
        $this->complaintCount = $complaintCount;
    }

    public function getAvgLatencyMs(): ?int
    {
        return $this->avgLatencyMs;
    }

    public function setAvgLatencyMs(?int $avgLatencyMs): void
    {
        $this->avgLatencyMs = $avgLatencyMs;
    }

    public function setOpenCount(int $openCount): void
    {
        $this->openCount = max(0, $openCount);
    }

    public function setClickCount(int $clickCount): void
    {
        $this->clickCount = max(0, $clickCount);
    }

    public function setDeferralCount(int $deferralCount): void
    {
        $this->deferralCount = max(0, $deferralCount);
    }

    public function setFailureCount(int $failureCount): void
    {
        $this->failureCount = max(0, $failureCount);
    }

    public function setInboxPlacementEstimate(?float $estimate): void
    {
        $this->inboxPlacementEstimate = $estimate === null ? null : number_format(max(0.0, min(100.0, $estimate)), 2, '.', '');
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
