<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model;

final class RoutingDecision
{
    /**
     * @param list<string> $rankedProviders
     * @param array<string, float> $scores
     * @param array<string, array<string, float>> $breakdown
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $primaryProvider,
        public readonly array $rankedProviders,
        public readonly array $scores,
        public readonly array $breakdown
    ) {
    }
}

