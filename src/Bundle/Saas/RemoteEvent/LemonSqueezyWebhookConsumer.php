<?php

declare(strict_types=1);

/*
 * This file is part of SolidWorx Platform project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidWorx\Platform\SaasBundle\RemoteEvent;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use SolidWorx\Platform\SaasBundle\Entity\WebhookEventLog;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\Event;
use SolidWorx\Platform\SaasBundle\Event\PaymentEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCancelledEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCreatedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionExpiredEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPausedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentFailedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentPaidEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentRecoveredEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentRefundedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionResumedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUnpausedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUpdatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

#[AsRemoteEventConsumer('lemon_squeezy')]
final readonly class LemonSqueezyWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
    ) {
    }

    #[Override]
    public function consume(RemoteEvent $event): void
    {
        if ($event instanceof SubscriptionRemoteEvent) {
            $domainEvent = $this->createSubscriptionEvent($event);
        } elseif ($event instanceof SubscriptionPaymentRemoteEvent) {
            $domainEvent = $this->createPaymentEvent($event);
        } else {
            return;
        }

        $this->eventDispatcher->dispatch($domainEvent);

        $log = $this->requestStack->getCurrentRequest()?->attributes->get('_webhook_event_log');

        if ($log instanceof WebhookEventLog) {
            $log->setEventType($event->getName());
            $log->setGatewayEventId($this->extractGatewayEventId($event));
            $log->setExternalSubscriptionId($event->subscriptionId->toBase58());
        }
    }

    private function createSubscriptionEvent(SubscriptionRemoteEvent $event): SubscriptionEvent
    {
        $eventClass = match ($event->event) {
            Event::SUBSCRIPTION_CREATED => SubscriptionCreatedEvent::class,
            Event::SUBSCRIPTION_UPDATED => SubscriptionUpdatedEvent::class,
            Event::SUBSCRIPTION_CANCELLED => SubscriptionCancelledEvent::class,
            Event::SUBSCRIPTION_RESUMED => SubscriptionResumedEvent::class,
            Event::SUBSCRIPTION_EXPIRED => SubscriptionExpiredEvent::class,
            Event::SUBSCRIPTION_PAUSED => SubscriptionPausedEvent::class,
            Event::SUBSCRIPTION_UNPAUSED => SubscriptionUnpausedEvent::class,
            default => throw new RejectWebhookException(message: sprintf('Unsupported event type: %s', $event->event->value)),
        };

        return new $eventClass(
            $event->subscriptionId,
            $event->subscription->id,
            $event->subscription,
        );
    }

    private function createPaymentEvent(SubscriptionPaymentRemoteEvent $event): PaymentEvent
    {
        $eventClass = match ($event->event) {
            Event::SUBSCRIPTION_PAYMENT_SUCCESS => SubscriptionPaymentPaidEvent::class,
            Event::SUBSCRIPTION_PAYMENT_FAILED => SubscriptionPaymentFailedEvent::class,
            Event::SUBSCRIPTION_PAYMENT_RECOVERED => SubscriptionPaymentRecoveredEvent::class,
            Event::SUBSCRIPTION_PAYMENT_REFUNDED => SubscriptionPaymentRefundedEvent::class,
            default => throw new RejectWebhookException(message: sprintf('Unsupported event type: %s', $event->event->value)),
        };

        return new $eventClass(
            $event->subscriptionId,
            $event->subscriptionInvoice->id,
            $event->subscriptionInvoice,
        );
    }

    private function extractGatewayEventId(RemoteEvent $event): ?string
    {
        $meta = $event->getPayload()['meta'] ?? null;

        if (! is_array($meta)) {
            return null;
        }

        $id = $meta['id'] ?? null;

        return is_string($id) ? $id : null;
    }
}
