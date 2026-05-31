<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderMetricBucket;

interface ProviderMetricBucketRepositoryInterface extends BaseRepositoryInterface
{
    public function findOneByProviderAndBucket(
        Provider $provider,
        \DateTimeImmutable $bucketStart,
        int $bucketIntervalMinutes,
    ): ?ProviderMetricBucket;

    /** @return list<ProviderMetricBucket> */
    public function findRecentForProvider(Provider $provider, int $limit = 24): array;
}

