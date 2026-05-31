<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use RuntimeException;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderGuardInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\JsonDslEvaluatorInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\RoutingDecisionCacheInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\RoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingDecision;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class RoutingEngine
{
    /**
     * @var list<RoutingStrategyInterface>
     */
    private array $strategies;

    /**
     * @param iterable<RoutingStrategyInterface> $strategies
     */
    public function __construct(
        iterable $strategies,
        private readonly ProviderGuardInterface $providerGuard,
        private readonly JsonDslEvaluatorInterface $dslEvaluator,
        private readonly ?RoutingDecisionCacheInterface $cache = null,
        private readonly int $cacheTtlSeconds = 30
    ) {
        $this->strategies = is_array($strategies) ? $strategies : iterator_to_array($strategies, false);
    }

    public function route(RoutingRequest $request, RoutingContext $context): RoutingDecision
    {
        $cacheKey = $request->requestId;
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $eligibleProviders = [];
        foreach ($context->providers as $provider => $profile) {
            if ($this->providerGuard->isEligible($profile, $request)) {
                $eligibleProviders[] = $provider;
            }
        }

        if ($eligibleProviders === []) {
            throw new RuntimeException('No eligible providers available for routing request: ' . $request->requestId);
        }

        $scores = [];
        foreach ($eligibleProviders as $provider) {
            $scores[$provider] = 0.0;
        }

        $breakdown = [];
        foreach ($this->strategies as $strategy) {
            if (!$strategy->supports($request)) {
                continue;
            }

            $before = $scores;
            $scores = $strategy->score($request, $context, $scores);

            foreach ($scores as $provider => $newScore) {
                $breakdown[$strategy->getName()][$provider] = $newScore - ($before[$provider] ?? 0.0);
            }
        }

        $dsl = $request->metadata['routing_dsl'] ?? null;
        if (is_array($dsl)) {
            foreach ($scores as $provider => $score) {
                $dslDelta = $this->dslEvaluator->evaluate($dsl, $request, $context, $provider);
                $scores[$provider] = $score + $dslDelta;
                $breakdown['dsl'][$provider] = $dslDelta;
            }
        }

        arsort($scores, SORT_NUMERIC);
        $rankedProviders = array_keys($scores);
        $primary = $rankedProviders[0] ?? null;
        if ($primary === null) {
            throw new RuntimeException('Routing engine could not pick a provider for request: ' . $request->requestId);
        }

        $decision = new RoutingDecision(
            requestId: $request->requestId,
            primaryProvider: $primary,
            rankedProviders: $rankedProviders,
            scores: $scores,
            breakdown: $breakdown
        );

        if ($this->cache !== null) {
            $this->cache->put($cacheKey, $decision, $this->cacheTtlSeconds);
        }

        return $decision;
    }
}

