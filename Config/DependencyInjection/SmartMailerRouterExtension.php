<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SmartMailerRouterExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('smart_mailer_router.config', $config);
        $container->setParameter('smart_mailer_router.default_profile', $config['default_profile']);
        $container->setParameter('smart_mailer_router.default_strategy', $config['default_strategy']);
        $container->setParameter('smart_mailer_router.providers', $config['providers']);
        $container->setParameter('smart_mailer_router.health', $config['health']);
        $container->setParameter('smart_mailer_router.warmup', $config['warmup']);
        $container->setParameter('smart_mailer_router.retry', $config['retry']);
        $container->setParameter('smart_mailer_router.maintenance', $config['maintenance']);
        $container->setParameter('smart_mailer_router.queues.routing', $config['queues']['routing']);
        $container->setParameter('smart_mailer_router.queues.warmup', $config['queues']['warmup']);
        $container->setParameter('smart_mailer_router.queues.logs', $config['queues']['logs']);
        $container->setParameter('smart_mailer_router.queues.dead_letter', $config['queues']['dead_letter']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
