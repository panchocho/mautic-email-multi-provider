<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class ExperimentBucketStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'experiment_bucket';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $bucket = (string) ($request->metadata['experiment_bucket'] ?? '');
        $rules = $request->metadata['experiment_allocations'] ?? [];
        if ($bucket === '' || !is_array($rules)) {
            return 0.0;
        }

        $weights = $rules[$bucket] ?? null;
        if (!is_array($weights)) {
            return 0.0;
        }

        $weight = $weights[$provider] ?? 0.0;

        return is_numeric($weight) ? (float) $weight : 0.0;
    }
}

