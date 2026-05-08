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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Subscription;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\ActiveSubscriptionPlanChangeException;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;

#[CoversClass(SubscriptionManager::class)]
final class SubscriptionManagerPlanChangeTest extends TestCase
{
    private SubscriptionRepositoryInterface&MockObject $subscriptionRepository;

    private PaymentIntegrationInterface&MockObject $paymentIntegration;

    private SubscriptionManager $manager;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->paymentIntegration = $this->createMock(PaymentIntegrationInterface::class);

        $this->manager = new SubscriptionManager(
            $this->subscriptionRepository,
            $this->createMock(PlanRepositoryInterface::class),
            $this->paymentIntegration,
        );
    }

    public function testChangeActivePlanDelegatesToIntegrationAndPersistsNewPlan(): void
    {
        $newRenewDate = new DateTimeImmutable('+30 days');
        $newPlan = $this->createMock(Plan::class);
        $subscription = new Subscription();

        $this->paymentIntegration
            ->expects(self::once())
            ->method('changePlan')
            ->with($subscription, $newPlan)
            ->willReturn($newRenewDate);

        $this->subscriptionRepository
            ->expects(self::once())
            ->method('save')
            ->with($subscription);

        $this->manager->changeActivePlan($subscription, $newPlan);

        self::assertSame($newPlan, $subscription->getPlan());
        self::assertEquals($newRenewDate, $subscription->getEndDate());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        self::assertNull($subscription->getPendingPlan());
        self::assertNull($subscription->getPendingPlanChangeAt());
    }

    public function testScheduleDowngradeStoresPendingPlanAndPersists(): void
    {
        $effectiveAt = new DateTimeImmutable('+15 days');
        $newPlan = $this->createMock(Plan::class);
        $subscription = new Subscription();

        $this->paymentIntegration
            ->expects(self::once())
            ->method('cancelAtPeriodEnd')
            ->with($subscription)
            ->willReturn($effectiveAt);

        $this->subscriptionRepository->expects(self::once())->method('save');

        $result = $this->manager->scheduleDowngrade($subscription, $newPlan);

        self::assertSame($effectiveAt, $result);
        self::assertSame($newPlan, $subscription->getPendingPlan());
        self::assertSame($effectiveAt, $subscription->getPendingPlanChangeAt());
        self::assertEquals($effectiveAt, $subscription->getEndDate());
    }

    public function testCancelScheduledDowngradeClearsPendingPlanAndResumes(): void
    {
        $renewDate = new DateTimeImmutable('+30 days');
        $existingPending = $this->createMock(Plan::class);
        $subscription = new Subscription();
        $subscription->setPendingPlan($existingPending);
        $subscription->setPendingPlanChangeAt(new DateTimeImmutable('+10 days'));

        $this->paymentIntegration
            ->expects(self::once())
            ->method('resume')
            ->with($subscription)
            ->willReturn($renewDate);

        $this->subscriptionRepository->expects(self::once())->method('save');

        $this->manager->cancelScheduledDowngrade($subscription);

        self::assertNull($subscription->getPendingPlan());
        self::assertNull($subscription->getPendingPlanChangeAt());
        self::assertEquals($renewDate, $subscription->getEndDate());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
    }

    public function testApplyScheduledPlanChangeSwitchesToFreePlan(): void
    {
        $freePlan = new Plan();
        $freePlan->setPlanId('0');
        $freePlan->setPrice(0);
        $freePlan->setName('Free');

        $subscription = new Subscription();
        $subscription->setPendingPlan($freePlan);
        $subscription->setPendingPlanChangeAt(new DateTimeImmutable('-1 minute'));

        $this->subscriptionRepository->expects(self::once())->method('save');

        $this->manager->applyScheduledPlanChange($subscription);

        self::assertSame($freePlan, $subscription->getPlan());
        self::assertNull($subscription->getPendingPlan());
        self::assertNull($subscription->getPendingPlanChangeAt());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
    }

    public function testApplyScheduledPlanChangeNoOpWhenNoPendingPlan(): void
    {
        $subscription = new Subscription();

        $this->subscriptionRepository->expects(self::never())->method('save');

        $this->manager->applyScheduledPlanChange($subscription);
    }

    public function testApplyScheduledPlanChangeToFreeClearsExternalSubscriptionId(): void
    {
        $freePlan = new Plan();
        $freePlan->setPlanId('0');
        $freePlan->setPrice(0);
        $freePlan->setName('Free');

        $subscription = new Subscription();
        $subscription->setSubscriptionId('ls_sub_42');
        $subscription->setPendingPlan($freePlan);
        $subscription->setPendingPlanChangeAt(new DateTimeImmutable('-1 minute'));

        $this->subscriptionRepository->expects(self::once())->method('save');

        $this->manager->applyScheduledPlanChange($subscription);

        self::assertNull($subscription->getSubscriptionId());
        self::assertFalse($subscription->isExternallyBilled());
    }

    public function testChangePlanAllowsActiveFreeSubscriptionToSwap(): void
    {
        $freePlan = new Plan();
        $freePlan->setPlanId('0');
        $freePlan->setPrice(0);
        $freePlan->setName('Free');

        $newPlan = $this->createMock(Plan::class);

        $subscription = new Subscription();
        $subscription->setPlan($freePlan);
        $subscription->setStatus(SubscriptionStatus::ACTIVE);

        $this->subscriptionRepository->expects(self::once())->method('save');

        $this->manager->changePlan($subscription, $newPlan);

        self::assertSame($newPlan, $subscription->getPlan());
    }

    public function testChangePlanRejectsActiveExternallyBilledSubscription(): void
    {
        $newPlan = $this->createMock(Plan::class);

        $subscription = new Subscription();
        $subscription->setPlan($this->createMock(Plan::class));
        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        $subscription->setSubscriptionId('ls_sub_99');

        $this->subscriptionRepository->expects(self::never())->method('save');

        $this->expectException(ActiveSubscriptionPlanChangeException::class);

        $this->manager->changePlan($subscription, $newPlan);
    }
}
