<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

interface JsonDslEvaluatorInterface
{
    /**
     * @param array<string, mixed> $dsl
     */
    public function evaluate(array $dsl, RoutingRequest $request, RoutingContext $context, string $provider): float;
}

