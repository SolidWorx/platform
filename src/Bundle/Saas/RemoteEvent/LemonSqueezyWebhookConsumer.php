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
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

#[AsRemoteEventConsumer('lemon_squeezy')]
final readonly class LemonSqueezyWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Override]
    public function consume(RemoteEvent $event): void
    {
        if (! $event instanceof SubscriptionRemoteEvent && ! $event instanceof SubscriptionPaymentRemoteEvent) {
            return;
        }

        /** @var SubscriptionRemoteEvent|SubscriptionPaymentRemoteEvent $event */

        [$eventClass, $object] = match ($event->event) {
            Event::SUBSCRIPTION_CREATED => [SubscriptionCreatedEvent::class, $event->subscription],
            Event::SUBSCRIPTION_UPDATED => [SubscriptionUpdatedEvent::class, $event->subscription],
            Event::SUBSCRIPTION_CANCELLED => [SubscriptionCancelledEvent::class, $event->subscription],
            Event::SUBSCRIPTION_RESUMED => [SubscriptionResumedEvent::class, $event->subscription],
            Event::SUBSCRIPTION_EXPIRED => [SubscriptionExpiredEvent::class, $event->subscription],
            Event::SUBSCRIPTION_PAUSED => [SubscriptionPausedEvent::class, $event->subscription],
            Event::SUBSCRIPTION_UNPAUSED => [SubscriptionUnpausedEvent::class, $event->subscription],
            Event::SUBSCRIPTION_PAYMENT_SUCCESS => [SubscriptionPaymentPaidEvent::class, $event->subscriptionInvoice],
            Event::SUBSCRIPTION_PAYMENT_FAILED => [SubscriptionPaymentFailedEvent::class, $event->subscriptionInvoice],
            Event::SUBSCRIPTION_PAYMENT_RECOVERED => [SubscriptionPaymentRecoveredEvent::class, $event->subscriptionInvoice],
            Event::SUBSCRIPTION_PAYMENT_REFUNDED => [SubscriptionPaymentRefundedEvent::class, $event->subscriptionInvoice],
            default => throw new RejectWebhookException(message: sprintf('Unsupported event type: %s', $event->event->value)),
        };

        $this->eventDispatcher->dispatch(new $eventClass(
            $event->subscriptionId,
            $object->id,
            match (true) {
                is_a($eventClass, SubscriptionEvent::class, true) => $event->subscription,
                is_a($eventClass, PaymentEvent::class, true) => $event->subscriptionInvoice,
                default => null,
            }
        ));
    }
}
