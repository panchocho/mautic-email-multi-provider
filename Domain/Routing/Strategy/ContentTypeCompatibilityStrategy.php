<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class ContentTypeCompatibilityStrategy extends AbstractRoutingStrategy
{
    public function getName(): string
    {
        return 'content_type_compatibility';
    }

    protected function providerDelta(string $provider, RoutingRequest $request, RoutingContext $context): float
    {
        $profile = $context->providers[$provider] ?? null;
        if ($profile === null) {
            return 0.0;
        }

        $requiredTags = $request->metadata['required_provider_tags'] ?? [];
        if (!is_array($requiredTags) || $requiredTags === []) {
            return 0.0;
        }

        $requiredTags = array_values(array_filter($requiredTags, 'is_string'));
        $intersection = array_intersect($requiredTags, $profile->tags);

        if ($intersection === []) {
            return -4.0;
        }

        return 1.5 * count($intersection);
    }
}

