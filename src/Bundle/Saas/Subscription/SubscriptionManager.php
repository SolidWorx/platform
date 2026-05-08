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
use DateTimeInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Entity\PlanPrice;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\ActiveSubscriptionPlanChangeException;
use SolidWorx\Platform\SaasBundle\Exception\InvalidPlanException;
use SolidWorx\Platform\SaasBundle\Exception\TrialConfigurationException;
use SolidWorx\Platform\SaasBundle\Integration\Options;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanPriceRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SubscriptionManager implements SubscriptionProviderInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanPriceRepositoryInterface $planPriceRepository,
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
        PlanPrice|Ulid|string $priceId,
    ): Subscription {
        $planPrice = $this->planPriceRepository->find($priceId);

        if (! $planPrice instanceof PlanPrice) {
            $priceIdString = match (true) {
                $priceId instanceof Ulid => $priceId->toBase58(),
                $priceId instanceof PlanPrice => $priceId->getVariantId(),
                default => $priceId,
            };

            throw new InvalidPlanException($priceIdString);
        }

        $subscription = new Subscription();
        $subscription->setSubscriber($subscribable);
        $subscription->setStatus(SubscriptionStatus::PENDING);
        $subscription->setStartDate(new DateTime('NOW'));
        $subscription->setEndDate((new DateTime('NOW')));
        $subscription->setPlanPrice($planPrice);

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
     * Swap the price (variant) on a subscription that has not yet been
     * activated. Plan changes for ACTIVE subscriptions go through the payment
     * integration and are intentionally not handled here.
     *
     * @throws ActiveSubscriptionPlanChangeException
     */
    public function changePlan(Subscription $subscription, PlanPrice $planPrice): void
    {
        if ($subscription->getStatus() === SubscriptionStatus::ACTIVE) {
            throw new ActiveSubscriptionPlanChangeException($subscription);
        }

        $subscription->setPlanPrice($planPrice);

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
}
