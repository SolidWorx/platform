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

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Exception\TrialConfigurationException;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;
use Symfony\Component\Uid\Ulid;

#[CoversClass(SubscriptionManager::class)]
final class SubscriptionManagerStartTrialTest extends TestCase
{
    private SubscriptionRepositoryInterface&MockObject $subscriptionRepository;

    private SubscriptionManager $manager;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);

        $this->manager = new SubscriptionManager(
            $this->subscriptionRepository,
            $this->createMock(PlanRepositoryInterface::class),
            $this->createMock(PaymentIntegrationInterface::class),
        );
    }

    public function testStartTrialWithExplicitEndDateSetsTrialStatus(): void
    {
        $endDate = new DateTimeImmutable('+30 days');
        $subscription = $this->buildSubscription(null);

        $this->subscriptionRepository
            ->expects(self::once())
            ->method('save')
            ->with($subscription);

        $this->manager->startTrial($subscription, $endDate);

        self::assertSame(SubscriptionStatus::TRIAL, $subscription->getStatus());
        self::assertSame($endDate, $subscription->getEndDate());
    }

    public function testStartTrialWithNullEndDateUsesPlansTrialDuration(): void
    {
        $duration = new DateInterval('P30D');
        $subscription = $this->buildSubscription($duration);

        $this->subscriptionRepository
            ->expects(self::once())
            ->method('save')
            ->with($subscription);

        $this->manager->startTrial($subscription);

        self::assertSame(SubscriptionStatus::TRIAL, $subscription->getStatus());
        // End date should be ~30 days from now
        self::assertGreaterThan(new DateTimeImmutable('+29 days'), $subscription->getEndDate());
    }

    public function testStartTrialThrowsWhenNoPlanDurationAndNoExplicitDate(): void
    {
        $subscription = $this->buildSubscription(null);

        $this->subscriptionRepository->expects(self::never())->method('save');

        $this->expectException(TrialConfigurationException::class);

        $this->manager->startTrial($subscription);
    }

    private function buildSubscription(?DateInterval $trialDuration): Subscription
    {
        $plan = $this->createMock(Plan::class);
        $plan->method('getTrialDuration')->willReturn($trialDuration);
        $plan->method('getPlanId')->willReturn('plan_test');

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getPlan')->willReturn($plan);
        $subscription->method('getId')->willReturn(new Ulid());

        $statusHolder = [SubscriptionStatus::PENDING];
        $endDateHolder = [null];

        $subscription->method('setStatus')->willReturnCallback(static function (SubscriptionStatus $s) use (&$statusHolder, $subscription): Subscription {
            $statusHolder[0] = $s;
            return $subscription;
        });
        $subscription->method('getStatus')->willReturnCallback(static function () use (&$statusHolder): SubscriptionStatus {
            return $statusHolder[0];
        });

        $subscription->method('setEndDate')->willReturnCallback(static function ($d) use (&$endDateHolder, $subscription): Subscription {
            $endDateHolder[0] = $d;
            return $subscription;
        });
        $subscription->method('getEndDate')->willReturnCallback(static function () use (&$endDateHolder) {
            return $endDateHolder[0];
        });

        $subscription->method('setStartDate')->willReturn($subscription);

        return $subscription;
    }
}
