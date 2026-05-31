<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\Provider;
use MauticPlugin\SmartMailerRouterBundle\Domain\Entity\ProviderMetricBucket;
use MauticPlugin\SmartMailerRouterBundle\Domain\Repository\ProviderMetricBucketRepositoryInterface;

final class ProviderMetricBucketRepository extends AbstractDoctrineRepository implements ProviderMetricBucketRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderMetricBucket::class);
    }

    public function findOneByProviderAndBucket(
        Provider $provider,
        \DateTimeImmutable $bucketStart,
        int $bucketIntervalMinutes,
    ): ?ProviderMetricBucket {
        return $this->findOneBy([
            'provider' => $provider,
            'bucketStart' => $bucketStart,
            'bucketIntervalMinutes' => $bucketIntervalMinutes,
        ]);
    }

    /** @return list<ProviderMetricBucket> */
    public function findRecentForProvider(Provider $provider, int $limit = 24): array
    {
        return $this->findBy(
            ['provider' => $provider],
            ['bucketStart' => 'DESC'],
            $limit
        );
    }
}

