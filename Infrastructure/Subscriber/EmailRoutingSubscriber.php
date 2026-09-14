<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Subscriber;

use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessor;
use MauticPlugin\SmartMailerRouterBundle\Application\Event\EmailRoutingRequestedEvent;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EmailRoutingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouteEmailProcessor $routeEmailProcessor,
        private readonly ?SmartMailerSettingsRepository $settingsRepository = null,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailRoutingRequestedEvent::class => 'onRoutingRequested',
        ];
    }

    public function onRoutingRequested(EmailRoutingRequestedEvent $event): void
    {
        if ($this->settingsRepository !== null && !$this->settingsRepository->isActive()) {
            return;
        }

        $this->routeEmailProcessor->process(new RouteEmailCommand(
            requestId: $event->requestId,
            tenantId: $event->tenantId,
            recipient: $event->recipient,
            messageType: $event->messageType,
            region: $event->region,
            routingMode: $event->routingMode,
            payload: $event->payload,
            metadata: $event->metadata
        ));
    }
}
