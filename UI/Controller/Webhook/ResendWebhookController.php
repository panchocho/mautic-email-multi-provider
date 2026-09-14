<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Webhook;

use MauticPlugin\SmartMailerRouterBundle\Application\Deliverability\ResendBounceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResendWebhookController
{
    public function __construct(
        private readonly ResendBounceService $bounceService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->bounceService->verifyWebhookSignature(
            $request->getContent(),
            (string) $request->headers->get('svix-id'),
            (string) $request->headers->get('svix-timestamp'),
            (string) $request->headers->get('svix-signature'),
        )) {
            return new JsonResponse(['error' => 'Invalid webhook signature'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = $this->bounceService->processWebhook($payload);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Webhook processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['ok' => true] + $result);
    }
}
