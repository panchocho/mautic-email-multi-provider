<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use RuntimeException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderGuardInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\RoutingDecisionCacheInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingDecision;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class ModeRoutingEngine
{
    public function __construct(
        private readonly ModeRoutingStrategyFactory $factory,
        private readonly ProviderGuardInterface $providerGuard,
        private readonly ?RoutingDecisionCacheInterface $cache = null
    ) {
    }

    public function route(RoutingRequest $request, RoutingContext $context, RoutingMode $mode): RoutingDecision
    {
        $cacheKey = sprintf('mode:%s:%s', $mode->value, $request->requestId);
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $strategy = $this->factory->forMode($mode);
        $ranked = $strategy->rank($request, $context);

        $eligible = [];
        foreach ($ranked as $providerName) {
            $profile = $context->providers[$providerName] ?? null;
            if ($profile === null) {
                continue;
            }
            if ($this->providerGuard->isEligible($profile, $request)) {
                $eligible[] = $providerName;
            }
        }

        if ($eligible === []) {
            throw new RuntimeException(sprintf('No eligible provider found for request "%s".', $request->requestId));
        }

        $scores = [];
        foreach ($eligible as $index => $provider) {
            $scores[$provider] = (float) (count($eligible) - $index);
        }

        $decision = new RoutingDecision(
            requestId: $request->requestId,
            primaryProvider: $eligible[0],
            rankedProviders: $eligible,
            scores: $scores,
            breakdown: ['mode' => $scores]
        );

        $this->cache?->put($cacheKey, $decision, 15);

        return $decision;
    }
}

