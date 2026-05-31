<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject;

enum RoutingMode: string
{
    case ROUND_ROBIN = 'round_robin';
    case WEIGHTED_ROUND_ROBIN = 'weighted_round_robin';
    case FAILOVER = 'failover';
    case STICKY_CAMPAIGN = 'sticky_campaign';
    case DOMAIN_ROUTING = 'domain_routing';
    case MX_ROUTING = 'mx_routing';
    case RANDOMIZED = 'randomized';
    case PERCENTAGE_SPLIT = 'percentage_split';
    case TAG_BASED = 'tag_based';
    case PRIORITY_BASED = 'priority_based';
    case COST_OPTIMIZED = 'cost_optimized';
    case ENGAGEMENT_BASED = 'engagement_based';
    case GEO_ROUTING = 'geo_routing';
    case MULTI_DOMAIN_ROUTING = 'multi_domain_routing';
}
