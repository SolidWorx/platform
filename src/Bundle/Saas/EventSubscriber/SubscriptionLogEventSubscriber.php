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

namespace SolidWorx\Platform\SaasBundle\EventSubscriber;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\SubscriptionInvoice;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Entity\WebhookEventLog;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionLogType;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCancelledEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCreatedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionExpiredEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPausedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentFailedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentPaidEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentRecoveredEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionPaymentRefundedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionResumedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUnpausedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUpdatedEvent;
use SolidWorx\Platform\SaasBundle\Exception\InvalidSubscriptionException;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

final readonly class SubscriptionLogEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionCreatedEvent::class => ['onSubscriptionCreated'],
            SubscriptionUpdatedEvent::class => ['onSubscriptionUpdated'],
            SubscriptionCancelledEvent::class => ['onSubscriptionCancelled'],
            SubscriptionExpiredEvent::class => ['onSubscriptionExpired'],
            SubscriptionPausedEvent::class => ['onSubscriptionPaused'],
            SubscriptionUnpausedEvent::class => ['onSubscriptionUnpaused'],
            SubscriptionResumedEvent::class => ['onSubscriptionResumed'],
            SubscriptionPaymentPaidEvent::class => ['onSubscriptionPaymentPaid'],
            SubscriptionPaymentFailedEvent::class => ['onSubscriptionPaymentFailed'],
            SubscriptionPaymentRecoveredEvent::class => ['onSubscriptionPaymentRecovered'],
            SubscriptionPaymentRefundedEvent::class => ['onSubscriptionPaymentRefunded'],
        ];
    }

    public function onSubscriptionCreated(SubscriptionCreatedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscription instanceof \SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription) {
            $metadata = [
                'planName' => $event->subscription->attributes->productName,
                'variantId' => $event->subscription->attributes->variantId,
                'endsAt' => $event->subscription->attributes->endsAt?->format(DateTimeInterface::ATOM),
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::CREATED, $metadata);
    }

    public function onSubscriptionUpdated(SubscriptionUpdatedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $logType = SubscriptionLogType::RENEWED;
        $metadata = null;

        if ($event->subscription instanceof \SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription) {
            $logType = match ($event->subscription->attributes->status) {
                SubscriptionStatus::ACTIVE => SubscriptionLogType::RENEWED,
                SubscriptionStatus::ON_TRIAL => SubscriptionLogType::TRIAL_STARTED,
                SubscriptionStatus::CANCELLED => SubscriptionLogType::CANCELLED,
                SubscriptionStatus::EXPIRED => SubscriptionLogType::EXPIRED,
                SubscriptionStatus::PAUSED => SubscriptionLogType::PAUSED,
                default => SubscriptionLogType::ACTIVATED,
            };

            $metadata = [
                'renewsAt' => $event->subscription->attributes->renewsAt?->format(DateTimeInterface::ATOM),
                'status' => $event->subscription->attributes->status->value,
            ];
        }

        $this->createLog($subscription, $logType, $metadata);
    }

    public function onSubscriptionCancelled(SubscriptionCancelledEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscription instanceof \SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription) {
            $metadata = [
                'endsAt' => $event->subscription->attributes->endsAt?->format(DateTimeInterface::ATOM),
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::CANCELLED, $metadata);
    }

    public function onSubscriptionExpired(SubscriptionExpiredEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $this->createLog($subscription, SubscriptionLogType::EXPIRED);
    }

    public function onSubscriptionPaused(SubscriptionPausedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $this->createLog($subscription, SubscriptionLogType::PAUSED);
    }

    public function onSubscriptionUnpaused(SubscriptionUnpausedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $this->createLog($subscription, SubscriptionLogType::UNPAUSED);
    }

    public function onSubscriptionResumed(SubscriptionResumedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscription instanceof \SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription) {
            $metadata = [
                'renewsAt' => $event->subscription->attributes->renewsAt?->format(DateTimeInterface::ATOM),
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::RESUMED, $metadata);
    }

    public function onSubscriptionPaymentPaid(SubscriptionPaymentPaidEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscriptionInvoice instanceof SubscriptionInvoice) {
            $metadata = [
                'invoiceId' => $event->subscriptionInvoice->id,
                'total' => $event->subscriptionInvoice->attributes->total,
                'currency' => $event->subscriptionInvoice->attributes->currency,
                'cardBrand' => $event->subscriptionInvoice->attributes->cardBrand,
                'cardLastFour' => $event->subscriptionInvoice->attributes->cardLastFour,
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::PAYMENT_PAID, $metadata);
    }

    public function onSubscriptionPaymentFailed(SubscriptionPaymentFailedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscriptionInvoice instanceof SubscriptionInvoice) {
            $metadata = [
                'invoiceId' => $event->subscriptionInvoice->id,
                'total' => $event->subscriptionInvoice->attributes->total,
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::PAYMENT_FAILED, $metadata);
    }

    public function onSubscriptionPaymentRecovered(SubscriptionPaymentRecoveredEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscriptionInvoice instanceof SubscriptionInvoice) {
            $metadata = [
                'invoiceId' => $event->subscriptionInvoice->id,
                'total' => $event->subscriptionInvoice->attributes->total,
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::PAYMENT_RECOVERED, $metadata);
    }

    public function onSubscriptionPaymentRefunded(SubscriptionPaymentRefundedEvent $event): void
    {
        $subscription = $this->findSubscription($event->subscriptionId);

        $metadata = null;

        if ($event->subscriptionInvoice instanceof SubscriptionInvoice) {
            $metadata = [
                'invoiceId' => $event->subscriptionInvoice->id,
                'total' => $event->subscriptionInvoice->attributes->total,
                'refunded' => $event->subscriptionInvoice->attributes->refunded,
            ];
        }

        $this->createLog($subscription, SubscriptionLogType::PAYMENT_REFUNDED, $metadata);
    }

    private function findSubscription(Ulid $subscriptionId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (! $subscription instanceof Subscription) {
            throw new InvalidSubscriptionException($subscriptionId->toBase58());
        }

        return $subscription;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function createLog(Subscription $subscription, SubscriptionLogType $type, ?array $metadata = null): void
    {
        $log = new SubscriptionLog();
        $log->setSubscription($subscription);
        $log->setType($type);
        $log->setMetadata($metadata);

        $webhookEventLog = $this->requestStack->getCurrentRequest()?->attributes->get('_webhook_event_log');

        if ($webhookEventLog instanceof WebhookEventLog) {
            $log->setWebhookEventLog($webhookEventLog);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
