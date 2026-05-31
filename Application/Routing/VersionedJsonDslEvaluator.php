<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Routing;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\JsonDslEvaluatorInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;

final class VersionedJsonDslEvaluator implements JsonDslEvaluatorInterface
{
    public function evaluate(array $dsl, RoutingRequest $request, RoutingContext $context, string $provider): float
    {
        $version = (string) ($dsl['version'] ?? '1');
        $rules = $dsl['rules'] ?? [];
        if (!is_array($rules)) {
            return 0.0;
        }

        $score = is_numeric($dsl['default'] ?? null) ? (float) $dsl['default'] : 0.0;

        foreach ($rules as $rule) {
            if (!is_array($rule) || !$this->matchesRule($rule, $request, $context, $provider)) {
                continue;
            }

            $weight = is_numeric($rule['weight'] ?? null) ? (float) $rule['weight'] : 0.0;
            if ($version === '2') {
                $mode = (string) ($rule['mode'] ?? 'add');
                if ($mode === 'multiply') {
                    $factor = is_numeric($rule['factor'] ?? null) ? (float) $rule['factor'] : 1.0;
                    $score *= $factor;
                    continue;
                }
            }

            $score += $weight;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function matchesRule(array $rule, RoutingRequest $request, RoutingContext $context, string $provider): bool
    {
        $when = $rule['when'] ?? null;
        if (!is_array($when)) {
            return false;
        }

        $field = (string) ($when['field'] ?? '');
        $op = (string) ($when['op'] ?? 'eq');
        $expected = $when['value'] ?? null;
        $actual = $this->resolveField($field, $provider, $request, $context);

        return match ($op) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && !in_array($actual, $expected, true),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            default => false,
        };
    }

    private function resolveField(string $field, string $provider, RoutingRequest $request, RoutingContext $context): mixed
    {
        return match ($field) {
            'request.tenant_id' => $request->tenantId,
            'request.campaign_type' => $request->campaignType,
            'request.region' => $request->region,
            'request.message_type' => $request->messageType,
            'request.priority' => $request->priority,
            'provider.name' => $provider,
            'provider.health' => $context->health[$provider]?->score,
            default => $this->resolveDynamicField($field, $provider, $request, $context),
        };
    }

    private function resolveDynamicField(string $field, string $provider, RoutingRequest $request, RoutingContext $context): mixed
    {
        if (str_starts_with($field, 'context.stats.')) {
            $metric = substr($field, strlen('context.stats.'));

            return $context->stats[$provider][$metric] ?? null;
        }

        if (str_starts_with($field, 'request.metadata.')) {
            $key = substr($field, strlen('request.metadata.'));

            return $request->metadata[$key] ?? null;
        }

        if (str_starts_with($field, 'provider.tags.')) {
            $tag = substr($field, strlen('provider.tags.'));
            $tags = $context->providers[$provider]?->tags ?? [];

            return in_array($tag, $tags, true);
        }

        return null;
    }
}

