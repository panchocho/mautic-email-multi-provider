<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Delivery;

use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;

interface RouteEmailProcessorInterface
{
    public function process(RouteEmailCommand $command): RouteEmailExecution;
}
