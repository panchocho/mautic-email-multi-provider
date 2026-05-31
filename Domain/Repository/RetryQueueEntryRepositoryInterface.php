<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use DateTimeImmutable;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\RetryQueueEntry;

interface RetryQueueEntryRepositoryInterface extends BaseRepositoryInterface
{
    /** @return list<RetryQueueEntry> */
    public function findDue(DateTimeImmutable $at, int $limit = 1000): array;
}

