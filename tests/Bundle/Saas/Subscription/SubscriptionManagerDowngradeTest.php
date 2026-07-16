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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\NoFreePlanConfiguredException;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;

#[CoversClass(SubscriptionManager::class)]
final class SubscriptionManagerDowngradeTest extends TestCase
{
    public function testDowngradeChangesPlanAndActivatesWhenNotAlreadyFree(): void
    {
        $free = $this->freePlan();
        $plans = self::createStub(PlanRepositoryInterface::class);
        $plans->method('findFree')->willReturn($free);

        $subs = self::createMock(SubscriptionRepositoryInterface::class);
        $subs->expects(self::atLeastOnce())->method('save');

        $subscription = (new Subscription())->setPlan($this->paidPlan())->setStatus(SubscriptionStatus::TRIAL);

        $this->manager($plans, $subs)->downgradeToFree($subscription);

        self::assertSame($free, $subscription->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
    }

    public function testDowngradeActivatesWithoutChangingPlanWhenAlreadyFree(): void
    {
        $free = $this->freePlan();
        $plans = self::createStub(PlanRepositoryInterface::class);
        $plans->method('findFree')->willReturn($free);

        $subs = self::createStub(SubscriptionRepositoryInterface::class);

        $subscription = (new Subscription())->setPlan($free)->setStatus(SubscriptionStatus::TRIAL);

        $this->manager($plans, $subs)->downgradeToFree($subscription);

        self::assertSame($free, $subscription->getPlan());
        self::assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
    }

    public function testDowngradeThrowsWhenNoFreePlanConfigured(): void
    {
        $plans = self::createStub(PlanRepositoryInterface::class);
        $plans->method('findFree')->willReturn(null);

        $subscription = (new Subscription())->setPlan($this->paidPlan())->setStatus(SubscriptionStatus::TRIAL);

        $this->expectException(NoFreePlanConfiguredException::class);

        $this->manager($plans, self::createStub(SubscriptionRepositoryInterface::class))->downgradeToFree($subscription);
    }

    private function freePlan(): Plan
    {
        return (new Plan())->setName('Free')->setPlanId('0')->setPrice(0)->setActive(true);
    }

    private function paidPlan(): Plan
    {
        return (new Plan())->setName('Pro')->setPlanId('pro')->setPrice(1900)->setActive(true);
    }

    private function manager(PlanRepositoryInterface $plans, SubscriptionRepositoryInterface $subs): SubscriptionManager
    {
        return new SubscriptionManager($subs, $plans, self::createStub(PaymentIntegrationInterface::class));
    }
}
