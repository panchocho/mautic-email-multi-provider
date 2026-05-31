<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

interface RoutingStrategyInterface
{
    public function getName(): string;

    public function supports(RoutingRequest $request): bool;

    /**
     * @param array<string, float> $currentScores
     *
     * @return array<string, float>
     */
    public function score(RoutingRequest $request, RoutingContext $context, array $currentScores): array;
}

