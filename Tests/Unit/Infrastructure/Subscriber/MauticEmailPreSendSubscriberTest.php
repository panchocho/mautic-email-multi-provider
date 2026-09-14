<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\Infrastructure\Subscriber;

use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailExecution;
use MauticPlugin\SmartMailerRouterBundle\Application\Delivery\RouteEmailProcessorInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderSendResult;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Subscriber\MauticEmailPreSendSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

final class MauticEmailPreSendSubscriberTest extends TestCase
{
    public function testRoutesInternalSendThroughRouterAndSkipsMailer(): void
    {
        /** @var RouteEmailProcessorInterface&MockObject $processor */
        $processor = $this->createMock(RouteEmailProcessorInterface::class);
        $processor->expects(self::once())
            ->method('process')
            ->with(self::callback(static function ($command): bool {
                return $command->recipient === 'fwyler@gmail.com'
                    && $command->messageType === 'transactional'
                    && $command->routingMode === 'failover'
                    && ($command->metadata['routing_profile'] ?? null) === 'default'
                    && ($command->metadata['internal_send'] ?? null) === true
                    && ($command->payload['subject'] ?? null) === 'Example subject'
                    && ($command->payload['html'] ?? null) === '<p>Hola Equipo</p>'
                    && ($command->payload['text'] ?? null) === 'Hola Equipo'
                    && ($command->payload['from_email'] ?? null) === 'sender@client.example'
                    && ($command->payload['reply_to'] ?? null) === 'reply@client.example'
                    && ($command->metadata['sender_domain'] ?? null) === 'client.example';
            }))
            ->willReturn(new RouteEmailExecution(
                primaryProvider: 'resend',
                providerCode: 'resend',
                result: new ProviderSendResult(
                    provider: 'resend',
                    accepted: true,
                    providerMessageId: 'resend-123'
                )
            ));

        $subscriber = new MauticEmailPreSendSubscriber($processor, 'default');
        $event = $this->createInternalSendEvent();

        $subscriber->onPreSend($event);

        self::assertTrue($event->isSkip());
        self::assertFalse($event->isFatal());
        self::assertSame([], $event->getErrors());
    }

    public function testRoutesNonInternalSendThroughRouterAndSkipsNativeMailer(): void
    {
        /** @var RouteEmailProcessorInterface&MockObject $processor */
        $processor = $this->createMock(RouteEmailProcessorInterface::class);
        $processor->expects(self::once())->method('process')->willReturn(new RouteEmailExecution(primaryProvider: 'resend', providerCode: 'resend', result: new ProviderSendResult(provider: 'resend', accepted: true)));

        $subscriber = new MauticEmailPreSendSubscriber($processor, 'default');
        $event = $this->createInternalSendEvent(false);

        $subscriber->onPreSend($event);

        self::assertTrue($event->isSkip());
        self::assertFalse($event->isFatal());
        self::assertSame([], $event->getErrors());
    }

    private function createInternalSendEvent(bool $internalSend = true): EmailSendEvent
    {
        /** @var MailHelper&MockObject $helper */
        $helper = $this->createMock(MailHelper::class);
        $helper->expects(self::once())->method('dispatchSendEvent');
        $helper->method('getSubject')->willReturn('Example subject');
        $helper->method('getBody')->willReturn('<p>Hola {contactfield=firstname|equipo}</p>');
        $helper->method('getPlainText')->willReturn('Hola {contactfield=firstname|equipo}');
        $helper->method('getTokens')->willReturn([
            '{contactfield=firstname|equipo}' => 'Equipo',
        ]);
        $helper->message = new MauticMessage();
        $helper->message->from(new Address('sender@client.example', 'Client'));
        $helper->message->replyTo(new Address('reply@client.example'));
        $helper->message->to(new Address('fwyler@gmail.com', 'Fwyler'));

        return new EmailSendEvent($helper, [
            'internalSend' => $internalSend,
            'subject' => 'Example subject',
            'content' => '<p>Hola {contactfield=firstname|equipo}</p>',
            'plainText' => 'Hola {contactfield=firstname|equipo}',
        ]);
    }
}
