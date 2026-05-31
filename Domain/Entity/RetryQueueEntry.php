<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'smr_retry_queue')]
#[ORM\Index(name: 'idx_smr_retry_status_next_attempt', columns: ['status', 'next_attempt_at'])]
class RetryQueueEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private string $messageId;

    #[ORM\Column(type: 'integer')]
    private int $attempt = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $nextAttemptAt;

    #[ORM\Column(type: 'string', length: 24)]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $messageId, DateTimeImmutable $nextAttemptAt)
    {
        $this->messageId = $messageId;
        $this->nextAttemptAt = $nextAttemptAt;
        $this->createdAt = new DateTimeImmutable();
    }
}

