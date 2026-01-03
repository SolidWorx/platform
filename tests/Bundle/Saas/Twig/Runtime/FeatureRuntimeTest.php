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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;
use SolidWorx\Platform\SaasBundle\Feature\FeatureValue;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Twig\Runtime\FeatureRuntime;

#[CoversClass(FeatureRuntime::class)]
final class FeatureRuntimeTest extends TestCase
{
    private PlanFeatureManager&MockObject $featureManager;

    private FeatureRuntime $runtime;

    private SubscribableInterface&MockObject $subscriber;

    protected function setUp(): void
    {
        $this->featureManager = $this->createMock(PlanFeatureManager::class);
        $this->runtime = new FeatureRuntime($this->featureManager);
        $this->subscriber = $this->createMock(SubscribableInterface::class);
    }

    public function testHasFeatureReturnsTrue(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('hasFeatureForSubscriber')
            ->with($this->subscriber, 'api_access')
            ->willReturn(true);

        self::assertTrue($this->runtime->hasFeature($this->subscriber, 'api_access'));
    }

    public function testHasFeatureReturnsFalse(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('hasFeatureForSubscriber')
            ->with($this->subscriber, 'api_access')
            ->willReturn(false);

        self::assertFalse($this->runtime->hasFeature($this->subscriber, 'api_access'));
    }

    public function testGetFeatureValueReturnsValue(): void
    {
        $featureValue = new FeatureValue('max_users', FeatureType::INTEGER, 50);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'max_users')
            ->willReturn($featureValue);

        self::assertSame(50, $this->runtime->getFeatureValue($this->subscriber, 'max_users'));
    }

    public function testGetFeatureValueReturnsFalseForUndefinedFeature(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'undefined_feature')
            ->willThrowException(new UndefinedFeatureException('undefined_feature'));

        self::assertFalse($this->runtime->getFeatureValue($this->subscriber, 'undefined_feature'));
    }

    public function testGetFeatureValueReturnsBooleanValue(): void
    {
        $featureValue = new FeatureValue('api_access', FeatureType::BOOLEAN, true);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'api_access')
            ->willReturn($featureValue);

        self::assertTrue($this->runtime->getFeatureValue($this->subscriber, 'api_access'));
    }

    public function testGetFeatureValueReturnsArrayValue(): void
    {
        $integrations = ['slack', 'jira', 'github'];
        $featureValue = new FeatureValue('integrations', FeatureType::ARRAY, $integrations);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'integrations')
            ->willReturn($featureValue);

        self::assertSame($integrations, $this->runtime->getFeatureValue($this->subscriber, 'integrations'));
    }

    public function testCanUseFeatureWithinLimit(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($this->subscriber, 'max_users', 5)
            ->willReturn(true);

        self::assertTrue($this->runtime->canUseFeature($this->subscriber, 'max_users', 5));
    }

    public function testCanUseFeatureAtLimit(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($this->subscriber, 'max_users', 10)
            ->willReturn(false);

        self::assertFalse($this->runtime->canUseFeature($this->subscriber, 'max_users', 10));
    }

    public function testCanUseFeatureDefaultUsageIsZero(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($this->subscriber, 'max_users', 0)
            ->willReturn(true);

        self::assertTrue($this->runtime->canUseFeature($this->subscriber, 'max_users'));
    }

    public function testGetRemainingQuotaReturnsValue(): void
    {
        $featureValue = new FeatureValue('max_users', FeatureType::INTEGER, 10);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'max_users')
            ->willReturn($featureValue);

        self::assertSame(5, $this->runtime->getRemainingQuota($this->subscriber, 'max_users', 5));
    }

    public function testGetRemainingQuotaReturnsNullForUnlimited(): void
    {
        $featureValue = new FeatureValue('max_users', FeatureType::INTEGER, FeatureValue::UNLIMITED);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'max_users')
            ->willReturn($featureValue);

        self::assertNull($this->runtime->getRemainingQuota($this->subscriber, 'max_users', 5));
    }

    public function testGetRemainingQuotaReturnsNullForUndefinedFeature(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'undefined_feature')
            ->willThrowException(new UndefinedFeatureException('undefined_feature'));

        self::assertNull($this->runtime->getRemainingQuota($this->subscriber, 'undefined_feature', 5));
    }

    public function testGetRemainingQuotaReturnsNullForBooleanFeature(): void
    {
        $featureValue = new FeatureValue('api_access', FeatureType::BOOLEAN, true);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'api_access')
            ->willReturn($featureValue);

        self::assertNull($this->runtime->getRemainingQuota($this->subscriber, 'api_access', 0));
    }

    public function testIsUnlimitedReturnsTrue(): void
    {
        $featureValue = new FeatureValue('max_users', FeatureType::INTEGER, FeatureValue::UNLIMITED);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'max_users')
            ->willReturn($featureValue);

        self::assertTrue($this->runtime->isUnlimited($this->subscriber, 'max_users'));
    }

    public function testIsUnlimitedReturnsFalse(): void
    {
        $featureValue = new FeatureValue('max_users', FeatureType::INTEGER, 10);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'max_users')
            ->willReturn($featureValue);

        self::assertFalse($this->runtime->isUnlimited($this->subscriber, 'max_users'));
    }

    public function testIsUnlimitedReturnsFalseForUndefinedFeature(): void
    {
        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'undefined_feature')
            ->willThrowException(new UndefinedFeatureException('undefined_feature'));

        self::assertFalse($this->runtime->isUnlimited($this->subscriber, 'undefined_feature'));
    }

    public function testIsUnlimitedReturnsFalseForBooleanFeature(): void
    {
        $featureValue = new FeatureValue('api_access', FeatureType::BOOLEAN, true);

        $this->featureManager
            ->expects($this->once())
            ->method('getFeatureForSubscriber')
            ->with($this->subscriber, 'api_access')
            ->willReturn($featureValue);

        self::assertFalse($this->runtime->isUnlimited($this->subscriber, 'api_access'));
    }
}
