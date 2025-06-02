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

namespace SolidWorx\Platform\SaasBundle\Subscription;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeInterface;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\InvalidPlanException;
use SolidWorx\Platform\SaasBundle\Integration\Options;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepository;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Uid\Ulid;
use function get_debug_type;

final readonly class SubscriptionManager
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private PlanRepository $planRepository,
        private PaymentIntegrationInterface $paymentIntegration,
    ) {
    }

    public function getSubscriptionFor(SubscribableInterface $subscriber): ?Subscription
    {
        return $this->subscriptionRepository->findOneBy([
            'subscriber' => $subscriber,
        ]);
    }

    public function createSubscription(
        SubscribableInterface $subscribable,
        Plan|Ulid|string $planId,
    ): Subscription {
        $plan = $this->planRepository->find($planId);

        if (! $plan instanceof Plan) {
            throw new InvalidPlanException(
                match (get_debug_type($planId)) {
                    'string' => $planId,
                    Ulid::class => $planId->toBase58(),
                    Plan::class => $planId->getPlanId(),
                },
            );
        }

        $subscription = new Subscription();
        $subscription->setSubscriber($subscribable);
        $subscription->setStatus(SubscriptionStatus::PENDING);
        $subscription->setStartDate(new DateTime('NOW'));
        $subscription->setEndDate((new DateTime('NOW')));
        $subscription->setPlan($plan);

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    public function getCheckoutUrl(Subscription $subscription, ?Options $options = null): string
    {
        return $this->paymentIntegration->checkout($subscription, $options);
    }

    public function getCustomerPortalUrl(Subscription $subscription): string
    {
        return $this->paymentIntegration->getCustomerPortalUrl($subscription);
    }

    public function startTrial(Subscription $subscription, DateTimeInterface $trialEndDate): void
    {
        $subscription->setStatus(SubscriptionStatus::TRIAL);
        $subscription->setStartDate(CarbonImmutable::now('UTC'));
        $subscription->setEndDate($trialEndDate);

        $this->subscriptionRepository->save($subscription);
    }

    public function renewSubscription(Subscription $subscription, DateTimeInterface $endDate): void
    {
        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        $subscription->setEndDate($endDate);

        $this->subscriptionRepository->save($subscription);
    }

    public function cancelSubscription(Subscription $subscription, DateTimeInterface $endsAt): void
    {
        $subscription->setStatus(SubscriptionStatus::CANCELLED);
        $subscription->setEndDate($endsAt);

        $this->subscriptionRepository->save($subscription);
    }

    public function expireSubscription(Subscription $subscription): void
    {
        $subscription->setStatus(SubscriptionStatus::EXPIRED);
        $subscription->setEndDate(CarbonImmutable::now('UTC'));

        $this->subscriptionRepository->save($subscription);
    }

    public function pauseSubscription(Subscription $subscription): void
    {
        $subscription->setStatus(SubscriptionStatus::PAUSED);

        $this->subscriptionRepository->save($subscription);
    }
}
