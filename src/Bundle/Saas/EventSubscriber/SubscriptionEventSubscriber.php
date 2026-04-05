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

use LogicException;
use Override;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionCreatedEvent;
use SolidWorx\Platform\SaasBundle\Event\SubscriptionUpdatedEvent;
use SolidWorx\Platform\SaasBundle\Exception\InvalidSubscriptionException;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use UnexpectedValueException;

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
        if ($event->subscription === null) {
            throw new LogicException(sprintf(
                'Cannot handle subscription status for subscription "%s": no subscription DTO was provided with the event.',
                $event->subscriptionId->toBase58(),
            ));
        }

        $attrs = $event->subscription->attributes;

        switch ($attrs->status) {
            case SubscriptionStatus::ACTIVE:
                $renewDate = $attrs->renewsAt ?? $attrs->endsAt;
                if ($renewDate === null) {
                    throw new UnexpectedValueException(sprintf(
                        'Cannot renew subscription "%s": both renewsAt and endsAt are null in the webhook payload.',
                        $event->subscriptionId->toBase58(),
                    ));
                }

                $this->subscriptionManager->renewSubscription($subscription, $renewDate);
                break;

            case SubscriptionStatus::ON_TRIAL:
                $this->subscriptionManager->startTrial($subscription, $attrs->trialEndsAt);
                break;

            case SubscriptionStatus::CANCELLED:
                $endsAt = $attrs->endsAt;
                if ($endsAt === null) {
                    throw new UnexpectedValueException(sprintf(
                        'Cannot cancel subscription "%s": endsAt is null in the webhook payload.',
                        $event->subscriptionId->toBase58(),
                    ));
                }

                $this->subscriptionManager->cancelSubscription($subscription, $endsAt);
                break;

            case SubscriptionStatus::EXPIRED:
                $this->subscriptionManager->expireSubscription($subscription);
                break;

            case SubscriptionStatus::PAUSED:
                $this->subscriptionManager->pauseSubscription($subscription);
                break;

            case SubscriptionStatus::PAST_DUE:
                $this->subscriptionManager->markAsPastDue($subscription);
                break;

            case SubscriptionStatus::UNPAID:
                $this->subscriptionManager->markAsUnpaid($subscription);
                break;

            default:
                throw new InvalidSubscriptionException(sprintf('Unsupported subscription status: %s', $attrs->status->name));
        }
    }
}
