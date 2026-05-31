<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use MauticPlugin\SmartMailerRouterBundle\Config\DependencyInjection\SmartMailerRouterExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class SmartMailerRouterBundle extends PluginBundleBase
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new SmartMailerRouterExtension();
        }

        return $this->extension;
    }
}
