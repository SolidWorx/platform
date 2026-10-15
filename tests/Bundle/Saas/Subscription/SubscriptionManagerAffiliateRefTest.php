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
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezyAffiliate;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(SubscriptionManager::class)]
final class SubscriptionManagerAffiliateRefTest extends TestCase
{
    public function testCreateSubscriptionStoresAffiliateRefFromCookie(): void
    {
        $this->assertSame('click_1', $this->create([
            LemonSqueezyAffiliate::COOKIE => 'click_1',
        ])->getAffiliateRef());
    }

    public function testCreateSubscriptionIgnoresInvalidAffiliateRef(): void
    {
        $this->assertNull($this->create([
            LemonSqueezyAffiliate::COOKIE => 'bad value',
        ])->getAffiliateRef());
    }

    /**
     * @param array<string, string> $cookies
     */
    private function create(array $cookies): Subscription
    {
        $plans = self::createStub(PlanRepositoryInterface::class);
        $plans->method('find')->willReturn(new Plan());

        $manager = new SubscriptionManager(
            self::createStub(SubscriptionRepositoryInterface::class),
            $plans,
            self::createStub(PaymentIntegrationInterface::class),
            new RequestStack([new Request(cookies: $cookies)]),
        );

        return $manager->createSubscription(self::createStub(SubscribableInterface::class), 'plan');
    }
}
