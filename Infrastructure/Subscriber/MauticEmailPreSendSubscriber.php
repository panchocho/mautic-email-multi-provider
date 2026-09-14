<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Subscriber;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessorInterface;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Address;

final class MauticEmailPreSendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouteEmailProcessorInterface $routeEmailProcessor,
        #[Autowire('%smart_mailer_router.default_profile%')]
        private readonly string $defaultProfile = 'default',
        private readonly ?SmartMailerSettingsRepository $settingsRepository = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_PRE_SEND => ['onPreSend', -255],
        ];
    }

    public function onPreSend(EmailSendEvent $event): void
    {
        if ($this->settingsRepository !== null && !$this->settingsRepository->isActive()) {
            return;
        }


        $helper = $event->getHelper();
        if ($helper === null || !isset($helper->message)) {
            return;
        }

        $toAddresses = $helper->message->getTo();
        if ($toAddresses === []) {
            return;
        }

        // Mautic normally replaces contact tokens after EMAIL_PRE_SEND. The router
        // skips that mailer path, so generate and apply them before routing.
        $helper->dispatchSendEvent();
        $tokens = $helper->getTokens();

        $messageType = $event->isInternalSend() ? 'transactional' : 'marketing';
        $from = $this->firstAddress($helper->message->getFrom());
        $replyTo = $this->firstAddress($helper->message->getReplyTo());

        $payload = [
            'subject' => $this->replaceTokens($helper->getSubject(), $tokens),
            'html' => $this->replaceTokens($helper->getBody(), $tokens),
            'text' => $this->replaceTokens($helper->getPlainText(), $tokens),
            'message_type' => $messageType,
            'source' => [
                'bundle' => 'EmailBundle',
                'action' => $event->isInternalSend() ? 'internal_send' : 'send',
            ],
        ];
        if ($from !== null) {
            $payload['from_email'] = $from->getAddress();
            $payload['from_name'] = $from->getName();
        }
        if ($replyTo !== null) {
            $payload['reply_to'] = $replyTo->getAddress();
        }
        if (($returnPath = $helper->message->getReturnPath()) !== null) {
            $payload['return_path'] = $returnPath->getAddress();
        }
        $senderDomain = $from === null ? null : $this->domainFromAddress($from->getAddress());

        $metadata = [
            'routing_profile' => $this->defaultProfile,
            'message_type' => $messageType,
            'sender_domain' => $senderDomain,
            'allowed_domains' => $senderDomain === null ? [] : [$senderDomain],
            'internal_send' => $event->isInternalSend(),
        ];

        foreach ($toAddresses as $toAddress) {
            if (!$toAddress instanceof Address) {
                continue;
            }

            $recipient = trim($toAddress->getAddress());
            if ($recipient === '') {
                continue;
            }

            try {
                $execution = $this->routeEmailProcessor->process(new RouteEmailCommand(
                    requestId: uniqid('mautic-', true),
                    tenantId: '',
                    recipient: $recipient,
                    messageType: $messageType,
                    region: 'us',
                    routingMode: 'failover',
                    payload: $payload,
                    metadata: $metadata
                ));

                if (!$execution->result->accepted) {
                    $reason = $execution->result->reason ?? 'routing_failed';
                    $event->addError(sprintf('%s: %s', $recipient, $reason));
                }
            } catch (\Throwable $exception) {
                $event->addError(sprintf('%s: %s', $recipient, $exception->getMessage()));

            }
        }

        $event->enableSkip();
    }

    /**
     * @param Address[] $addresses
     */
    private function firstAddress(array $addresses): ?Address
    {
        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                return $address;
            }
        }

        return null;
    }

    private function domainFromAddress(string $address): ?string
    {
        $domain = strtolower(trim((string) strrchr($address, '@')));

        return $domain === '' ? null : ltrim($domain, '@');
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function replaceTokens(string $content, array $tokens): string
    {
        if ($content === '' || $tokens === []) {
            return $content;
        }

        return str_ireplace(array_keys($tokens), array_map(static fn (mixed $value): string => (string) $value, $tokens), $content);
    }
}
