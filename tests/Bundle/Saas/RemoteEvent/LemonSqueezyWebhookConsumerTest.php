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

namespace Bundle\Saas\RemoteEvent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription;
use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\SubscriptionAttributes;
use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\SubscriptionInvoice;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\Event;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionStatus;
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
use SolidWorx\Platform\SaasBundle\RemoteEvent\LemonSqueezyWebhookConsumer;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionPaymentRemoteEvent;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionRemoteEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

#[CoversClass(LemonSqueezyWebhookConsumer::class)]
final class LemonSqueezyWebhookConsumerTest extends TestCase
{
    /**
     * Tests that the consumer correctly dispatches events based on the remote event type.
     *
     * @param RemoteEvent $remoteEvent The remote event to consume
     * @param class-string<SubscriptionRemoteEvent|SubscriptionPaymentRemoteEvent> $expectedEventClass The expected event class to be dispatched
     * @throws Exception
     */
    #[DataProvider('eventProvider')]
    public function testConsume(RemoteEvent $remoteEvent, string $expectedEventClass): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(function (SymfonyEvent $event) use ($expectedEventClass, $remoteEvent): bool {
                    // Verify the event is of the expected class
                    $this->assertInstanceOf($expectedEventClass, $event);

                    // Verify common properties for subscription events
                    if ($event instanceof SubscriptionEvent && $remoteEvent instanceof SubscriptionRemoteEvent) {
                        $this->assertSame($remoteEvent->subscriptionId, $event->subscriptionId, 'Subscription ID should match');
                        $this->assertSame($remoteEvent->subscription->id, $event->externalId, 'External ID should match');
                        $this->assertSame($remoteEvent->subscription, $event->subscription, 'Subscription object should be passed through');
                    }

                    // Verify common properties for payment events
                    if ($event instanceof PaymentEvent && $remoteEvent instanceof SubscriptionPaymentRemoteEvent) {
                        $this->assertSame($remoteEvent->subscriptionId, $event->subscriptionId, 'Subscription ID should match');
                        $this->assertSame($remoteEvent->subscriptionInvoice->id, $event->externalId, 'External ID should match');
                        $this->assertSame($remoteEvent->subscriptionInvoice, $event->subscriptionInvoice, 'Invoice object should be passed through');
                    }

                    return true;
                })
            )
            ->willReturnArgument(0);

        $consumer = new LemonSqueezyWebhookConsumer($dispatcher);
        $consumer->consume($remoteEvent);
    }

    /**
     * Tests that the consumer ignores remote events that are not subscription or payment related.
     */
    public function testConsumeIgnoresNonSubscriptionEvents(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $consumer = new LemonSqueezyWebhookConsumer($dispatcher);

        $consumer->consume(new RemoteEvent('name', 'text', []));
    }

    /**
     * Provides test cases for the consume method.
     *
     * Each test case includes:
     * - The remote event to consume
     * - The expected event class to be dispatched
     * - Additional properties to verify on the dispatched event
     *
     * @return iterable<array{RemoteEvent, string}>
     */
    public static function eventProvider(): iterable
    {
        $subscriptionId = new Ulid();

        // Regular active subscription
        $activeSubscription = new Subscription();
        $activeSubscription->id = 'sub_1234567890abcdef';
        $activeSubscription->attributes = new SubscriptionAttributes();
        $activeSubscription->attributes->status = SubscriptionStatus::ACTIVE;

        // Trial subscription
        $trialSubscription = new Subscription();
        $trialSubscription->id = 'sub_trial_1234567890';
        $trialSubscription->attributes = new SubscriptionAttributes();
        $trialSubscription->attributes->status = SubscriptionStatus::ON_TRIAL;

        // Subscription invoice
        $subscriptionInvoice = new SubscriptionInvoice();
        $subscriptionInvoice->id = 'inv_1234567890abcdef';

        // Test case 1: Subscription created (active)
        yield 'subscription created (active)' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_CREATED, []),
            SubscriptionCreatedEvent::class,
        ];

        // Test case 2: Subscription created (trial)
        yield 'subscription created (trial)' => [
            new SubscriptionRemoteEvent($subscriptionId, $trialSubscription, Event::SUBSCRIPTION_CREATED, []),
            SubscriptionCreatedEvent::class,
        ];

        // Test case 3: Subscription updated
        yield 'subscription updated' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_UPDATED, []),
            SubscriptionUpdatedEvent::class,
        ];

        // Test case 4: Subscription cancelled
        yield 'subscription cancelled' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_CANCELLED, []),
            SubscriptionCancelledEvent::class,
        ];

        // Test case 5: Subscription resumed
        yield 'subscription resumed' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_RESUMED, []),
            SubscriptionResumedEvent::class,
        ];

        // Test case 6: Subscription expired
        yield 'subscription expired' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_EXPIRED, []),
            SubscriptionExpiredEvent::class,
        ];

        // Test case 7: Subscription paused
        yield 'subscription paused' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_PAUSED, []),
            SubscriptionPausedEvent::class,
        ];

        // Test case 8: Subscription unpaused
        yield 'subscription unpaused' => [
            new SubscriptionRemoteEvent($subscriptionId, $activeSubscription, Event::SUBSCRIPTION_UNPAUSED, []),
            SubscriptionUnpausedEvent::class,
        ];

        // Test case 9: Subscription payment success
        yield 'subscription payment success' => [
            new SubscriptionPaymentRemoteEvent($subscriptionId, $subscriptionInvoice, Event::SUBSCRIPTION_PAYMENT_SUCCESS, []),
            SubscriptionPaymentPaidEvent::class,
        ];

        // Test case 10: Subscription payment failed
        yield 'subscription payment failed' => [
            new SubscriptionPaymentRemoteEvent($subscriptionId, $subscriptionInvoice, Event::SUBSCRIPTION_PAYMENT_FAILED, []),
            SubscriptionPaymentFailedEvent::class,
        ];

        // Test case 11: Subscription payment recovered
        yield 'subscription payment recovered' => [
            new SubscriptionPaymentRemoteEvent($subscriptionId, $subscriptionInvoice, Event::SUBSCRIPTION_PAYMENT_RECOVERED, []),
            SubscriptionPaymentRecoveredEvent::class,
        ];

        // Test case 12: Subscription payment refunded
        yield 'subscription payment refunded' => [
            new SubscriptionPaymentRemoteEvent($subscriptionId, $subscriptionInvoice, Event::SUBSCRIPTION_PAYMENT_REFUNDED, []),
            SubscriptionPaymentRefundedEvent::class,
        ];
    }
}
