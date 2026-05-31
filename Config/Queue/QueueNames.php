<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Config\Queue;

final class QueueNames
{
    public function __construct(
        public readonly string $routing,
        public readonly string $warmup,
        public readonly string $logs,
        public readonly string $deadLetter,
    ) {
    }
}
