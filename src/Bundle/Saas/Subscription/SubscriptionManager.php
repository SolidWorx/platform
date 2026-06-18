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
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\ActiveSubscriptionPlanChangeException;
use SolidWorx\Platform\SaasBundle\Exception\InvalidPlanException;
use SolidWorx\Platform\SaasBundle\Exception\TrialConfigurationException;
use SolidWorx\Platform\SaasBundle\Integration\Options;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SubscriptionManager implements SubscriptionProviderInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanRepositoryInterface $planRepository,
        private PaymentIntegrationInterface $paymentIntegration,
    ) {
    }

    #[Override]
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
            $planIdString = match (true) {
                $planId instanceof Ulid => $planId->toBase58(),
                $planId instanceof Plan => $planId->getPlanId(),
                default => $planId,
            };

            throw new InvalidPlanException($planIdString);
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

    public function startTrial(Subscription $subscription, ?DateTimeInterface $trialEndDate = null): void
    {
        if (! $trialEndDate instanceof DateTimeInterface) {
            $trialDuration = $subscription->getPlan()->getTrialDuration();

            if (! $trialDuration instanceof DateInterval) {
                throw new TrialConfigurationException(sprintf(
                    'Cannot start trial for subscription "%s": plan "%s" has no trial duration and no explicit end date was provided.',
                    $subscription->getId()->toBase58(),
                    $subscription->getPlan()->getPlanId(),
                ));
            }

            $trialEndDate = CarbonImmutable::now('UTC')->add($trialDuration);
        }

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

    public function markAsPastDue(Subscription $subscription): void
    {
        $subscription->setStatus(SubscriptionStatus::PAST_DUE);

        $this->subscriptionRepository->save($subscription);
    }

    public function markAsUnpaid(Subscription $subscription): void
    {
        $subscription->setStatus(SubscriptionStatus::UNPAID);

        $this->subscriptionRepository->save($subscription);
    }

    /**
     * Swap the plan on a subscription that is either pending or already
     * active on the free plan (i.e. not externally billed). Plan changes
     * for externally-billed ACTIVE subscriptions must go through the
     * payment integration via {@see self::changeActivePlan()}.
     *
     * @throws ActiveSubscriptionPlanChangeException
     */
    public function changePlan(Subscription $subscription, Plan $plan): void
    {
        if ($subscription->getStatus() === SubscriptionStatus::ACTIVE && $subscription->isExternallyBilled()) {
            throw new ActiveSubscriptionPlanChangeException($subscription);
        }

        $subscription->setPlan($plan);

        $this->subscriptionRepository->save($subscription);
    }

    /**
     * Marks a subscription as ACTIVE without going through the payment
     * integration. Intended for free plans, where there is no external
     * billing reference.
     */
    public function activate(Subscription $subscription, ?DateTimeInterface $endDate = null): void
    {
        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        $subscription->setStartDate(CarbonImmutable::now('UTC'));
        $subscription->setEndDate($endDate ?? CarbonImmutable::now('UTC')->addYears(100));

        $this->subscriptionRepository->save($subscription);
    }

    /**
     * Switch the plan on an already-active, externally-billed subscription
     * via the payment integration. Persists the new plan and the renew date
     * returned by the provider. Clears any previously scheduled downgrade.
     */
    public function changeActivePlan(Subscription $subscription, Plan $newPlan): void
    {
        $renewDate = $this->paymentIntegration->changePlan($subscription, $newPlan);

        $subscription->setPlan($newPlan);
        $subscription->setEndDate($renewDate);
        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        $subscription->setPendingPlan(null);
        $subscription->setPendingPlanChangeAt(null);

        $this->subscriptionRepository->save($subscription);
    }

    /**
     * Schedule a plan switch (typically a downgrade) for the end of the
     * current paid period. The current plan keeps applying until then.
     */
    public function scheduleDowngrade(Subscription $subscription, Plan $newPlan): DateTimeImmutable
    {
        $effectiveAt = $this->paymentIntegration->cancelAtPeriodEnd($subscription);

        $subscription->setPendingPlan($newPlan);
        $subscription->setPendingPlanChangeAt($effectiveAt);
        $subscription->setEndDate($effectiveAt);

        $this->subscriptionRepository->save($subscription);

        return $effectiveAt;
    }

    /**
     * Cancel a previously scheduled downgrade and resume the current plan.
     */
    public function cancelScheduledDowngrade(Subscription $subscription): void
    {
        $renewDate = $this->paymentIntegration->resume($subscription);

        $subscription->setPendingPlan(null);
        $subscription->setPendingPlanChangeAt(null);
        $subscription->setEndDate($renewDate);
        $subscription->setStatus(SubscriptionStatus::ACTIVE);

        $this->subscriptionRepository->save($subscription);
    }

    /**
     * Apply a previously scheduled plan switch. Called when the period ends
     * (e.g. from the LS subscription_expired webhook). Only operates if the
     * pending plan is still set.
     */
    public function applyScheduledPlanChange(Subscription $subscription): void
    {
        $pendingPlan = $subscription->getPendingPlan();

        if (! $pendingPlan instanceof Plan) {
            return;
        }

        $subscription->setPlan($pendingPlan);
        $subscription->setPendingPlan(null);
        $subscription->setPendingPlanChangeAt(null);

        if ($pendingPlan->isFree()) {
            $subscription->setStatus(SubscriptionStatus::ACTIVE);
            $subscription->setStartDate(CarbonImmutable::now('UTC'));
            $subscription->setEndDate(CarbonImmutable::now('UTC')->addYears(100));
            // Drop the now-stale external billing id so the subscription
            // matches the canonical free-plan shape (active, no provider id).
            $subscription->setSubscriptionId(null);
        }

        $this->subscriptionRepository->save($subscription);
    }
}
