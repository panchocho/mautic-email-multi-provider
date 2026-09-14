<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Subscriber;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use MauticPlugin\SmartMailerRouterBundle\Application\Deliverability\ResendBounceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ResendTransportWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ResendBounceService $bounceService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EmailEvents::ON_TRANSPORT_WEBHOOK => 'onTransportWebhook'];
    }

    public function onTransportWebhook(TransportWebhookEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->bounceService->verifyWebhookSignature(
            $request->getContent(),
            (string) $request->headers->get('svix-id'),
            (string) $request->headers->get('svix-timestamp'),
            (string) $request->headers->get('svix-signature'),
        )) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid webhook signature'], 401));
            return;
        }
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !in_array(strtolower((string) ($payload['type'] ?? '')), ['email.bounced', 'email.suppressed'], true)) {
            return;
        }

        try {
            $result = $this->bounceService->processWebhook($payload);
            $event->setResponse(new JsonResponse(['ok' => true] + $result));
        } catch (\Throwable) {
            $event->setResponse(new JsonResponse(['error' => 'Webhook processing failed'], 500));
        }
    }
}
