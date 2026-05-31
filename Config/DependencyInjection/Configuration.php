<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('smart_mailer_router');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_profile')->defaultValue('default')->end()
                ->scalarNode('default_strategy')->defaultValue('round_robin')->end()
                ->arrayNode('providers')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->scalarNode('type')->defaultValue('smtp_generic')->end()
                            ->integerNode('weight')->defaultValue(100)->end()
                            ->integerNode('priority')->defaultValue(100)->end()
                            ->integerNode('throughput_limit')->defaultValue(1000)->end()
                            ->floatNode('cost_per_email')->defaultValue(0.0)->end()
                            ->floatNode('reputation')->defaultValue(100.0)->end()
                            ->arrayNode('domains')->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('tags')->scalarPrototype()->end()->defaultValue([])->end()
                            ->scalarNode('notes')->defaultNull()->end()
                            ->arrayNode('config')->variablePrototype()->end()->defaultValue([])->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('health')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('min_score')->min(0)->max(100)->defaultValue(60)->end()
                        ->floatNode('bounce_threshold')->defaultValue(0.03)->end()
                        ->floatNode('complaint_threshold')->defaultValue(0.001)->end()
                        ->floatNode('defer_threshold')->defaultValue(0.06)->end()
                        ->arrayNode('weights')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->floatNode('success_rate')->defaultValue(0.65)->end()
                                ->floatNode('latency_ms')->defaultValue(0.20)->end()
                                ->floatNode('bounce_rate')->defaultValue(0.10)->end()
                                ->floatNode('complaint_rate')->defaultValue(0.05)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('warmup')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('default_ramp')
                            ->integerPrototype()->end()
                            ->defaultValue([100, 500, 1000, 2500, 5000])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('queues')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('routing')->defaultValue('smart_mailer.routing')->end()
                        ->scalarNode('warmup')->defaultValue('smart_mailer.warmup')->end()
                        ->scalarNode('logs')->defaultValue('smart_mailer.logs')->end()
                        ->scalarNode('dead_letter')->defaultValue('smart_mailer.dead_letter')->end()
                    ->end()
                ->end()
                ->arrayNode('retry')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_retries')->defaultValue(8)->end()
                        ->integerNode('base_delay_ms')->defaultValue(2000)->end()
                        ->floatNode('multiplier')->defaultValue(2.0)->end()
                        ->integerNode('max_delay_ms')->defaultValue(300000)->end()
                    ->end()
                ->end()
                ->arrayNode('maintenance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('delivery_log_retention_days')->defaultValue(90)->end()
                        ->integerNode('delivery_archive_retention_days')->defaultValue(180)->end()
                        ->integerNode('health_history_retention_days')->defaultValue(180)->end()
                        ->integerNode('retry_retention_days')->defaultValue(30)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
