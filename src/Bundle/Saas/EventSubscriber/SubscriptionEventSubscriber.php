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

use Override;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCreatedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUpdatedEvent;
use SolidWorx\Platform\SaasBundle\Exception\InvalidSubscriptionException;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class SubscriptionEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionManager $subscriptionManager,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionCreatedEvent::class => ['onSubscriptionCreated'],
            SubscriptionUpdatedEvent::class => ['onSubscriptionUpdated'],
        ];
    }

    public function onSubscriptionUpdated(SubscriptionUpdatedEvent $event): void
    {
        $subscription = $this->subscriptionRepository->find($event->subscriptionId);

        if (! $subscription instanceof Subscription) {
            throw new InvalidSubscriptionException($event->subscriptionId->toBase58());
        }

        $this->handleSubscriptionStatus($event, $subscription);
    }

    public function onSubscriptionCreated(SubscriptionCreatedEvent $event): void
    {
        $subscription = $this->subscriptionRepository->find($event->subscriptionId);

        if (! $subscription instanceof Subscription) {
            throw new InvalidSubscriptionException($event->subscriptionId->toBase58());
        }

        $subscription->setSubscriptionId($event->externalId);

        $this->handleSubscriptionStatus($event, $subscription);
    }

    protected function handleSubscriptionStatus(SubscriptionCreatedEvent | SubscriptionUpdatedEvent $event, Subscription $subscription): void
    {
        switch ($event->subscription->attributes->status) {
            case SubscriptionStatus::ACTIVE:
                $this->subscriptionManager->renewSubscription(
                    $subscription,
                    $event->subscription->attributes->renewsAt ?? $event->subscription->attributes->endsAt,
                );
                break;
            case SubscriptionStatus::ON_TRIAL:
                $this->subscriptionManager->startTrial(
                    $subscription,
                    $event->subscription->attributes->trialEndsAt,
                );
                break;
            case SubscriptionStatus::CANCELLED:
                $this->subscriptionManager->cancelSubscription(
                    $subscription,
                    $event->subscription->attributes->endsAt,
                );
                break;
            case SubscriptionStatus::EXPIRED:
                $this->subscriptionManager->expireSubscription($subscription);
                break;
            case SubscriptionStatus::PAUSED:
                $this->subscriptionManager->pauseSubscription($subscription);
                break;
            case SubscriptionStatus::UNPAID:
            case SubscriptionStatus::PAST_DUE:
                // Handle past due status if necessary, e.g., log or notify
                break;
            default:
                throw new InvalidSubscriptionException(sprintf('Unsupported subscription status: %s', $event->subscription->attributes->status->name));
        }
    }
}
