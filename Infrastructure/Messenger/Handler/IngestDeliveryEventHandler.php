<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Handler;

use MauticPlugin\SmartMailerRouterBundle\Application\Health\HealthScoreService;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Deliverability\DeliveryEventSignalExtractor;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\ApplyAutoActionsCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\IngestDeliveryEventCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RecomputeHealthCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class IngestDeliveryEventHandler
{
    public function __construct(
        private readonly DeliveryEventSignalExtractor $signalExtractor,
        private readonly HealthScoreService $healthScoreService,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function __invoke(IngestDeliveryEventCommand $command): void
    {
        $signal = $this->signalExtractor->extract($command->event);
        $providerName = (string) ($command->event['provider'] ?? 'unknown');

        $score = $this->healthScoreService->scoreFromSignal([
            'accepted' => (bool) ($signal['delivered'] ?? false) || (bool) ($command->event['accepted'] ?? false),
            'latency_ms' => (int) ($signal['latency_ms'] ?? 0),
            'bounced' => (bool) ($signal['bounce'] ?? false),
            'complaint' => (bool) ($signal['complaint'] ?? false),
            'deferred' => (bool) ($signal['deferred'] ?? false),
        ]);

        $this->bus->dispatch(new RecomputeHealthCommand($providerName));
        $this->bus->dispatch(new ApplyAutoActionsCommand($providerName, [
            'signal' => $signal,
            'score' => $score,
        ]));
    }
}
