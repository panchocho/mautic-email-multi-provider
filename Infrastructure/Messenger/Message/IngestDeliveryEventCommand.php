<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message;

final class IngestDeliveryEventCommand
{
    /**
     * @param array<string, mixed> $event
     */
    public function __construct(public readonly array $event)
    {
    }
}

