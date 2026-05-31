<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Strategy\Mode;

use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Contract\ModeRoutingStrategyInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingContext;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;

final class StickyCampaignModeStrategy extends AbstractModeStrategy implements ModeRoutingStrategyInterface
{
    public function mode(): RoutingMode
    {
        return RoutingMode::STICKY_CAMPAIGN;
    }

    public function rank(RoutingRequest $request, RoutingContext $context): array
    {
        $providers = array_keys($this->providers($context));
        if ($providers === []) {
            return [];
        }

        $campaignId = (string) ($request->metadata['campaign_id'] ?? $request->requestId);
        $index = abs(crc32('campaign:' . $campaignId)) % count($providers);
        return array_merge(array_slice($providers, $index), array_slice($providers, 0, $index));
    }
}

