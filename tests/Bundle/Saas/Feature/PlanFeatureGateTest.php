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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureType;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;
use SolidWorx\Platform\PlatformBundle\Feature\NullSubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\PlanReference;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\UpgradeOptions;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureGate;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use Symfony\Component\Uid\Ulid;

#[CoversClass(PlanFeatureGate::class)]
#[UsesClass(FeatureValue::class)]
#[UsesClass(NullSubscriberResolver::class)]
#[UsesClass(PlanReference::class)]
#[UsesClass(UpgradeOptions::class)]
#[UsesClass(Plan::class)]
final class PlanFeatureGateTest extends TestCase
{
    public function testResolveWithExplicitSubscriberDelegatesToManager(): void
    {
        $manager = $this->createMock(PlanFeatureManager::class);
        $subscriber = $this->subscriber();
        $expected = new FeatureValue('max_clients', FeatureType::INTEGER, 50);

        $manager->expects(self::once())
            ->method('getFeatureForSubscriber')
            ->with($subscriber, 'max_clients')
            ->willReturn($expected);

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());

        self::assertSame($expected, $gate->resolve('max_clients', $subscriber));
    }

    public function testResolveWithoutSubscriberFallsBackToConfigDefault(): void
    {
        $manager = $this->createMock(PlanFeatureManager::class);
        $expected = new FeatureValue('max_clients', FeatureType::INTEGER, 5);

        $manager->expects(self::once())
            ->method('getConfigDefault')
            ->with('max_clients')
            ->willReturn($expected);

        $manager->expects(self::never())->method('getFeatureForSubscriber');

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());

        self::assertSame($expected, $gate->resolve('max_clients'));
    }

    public function testResolveUsesResolverWhenSubscriberOmitted(): void
    {
        $manager = $this->createMock(PlanFeatureManager::class);
        $subscriber = $this->subscriber();
        $expected = new FeatureValue('custom_branding', FeatureType::BOOLEAN, true);

        $resolver = $this->createMock(SubscriberResolver::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($subscriber);

        $manager->expects(self::once())
            ->method('getFeatureForSubscriber')
            ->with($subscriber, 'custom_branding')
            ->willReturn($expected);

        $gate = new PlanFeatureGate($manager, $resolver);

        self::assertSame($expected, $gate->resolve('custom_branding'));
    }

    public function testIsEnabledDelegatesToFeatureValue(): void
    {
        $manager = self::createStub(PlanFeatureManager::class);
        $value = new FeatureValue('flag', FeatureType::BOOLEAN, true);
        $manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());

        self::assertTrue($gate->isEnabled('flag'));
    }

    public function testCanUseDelegatesToFeatureValueAllows(): void
    {
        $manager = self::createStub(PlanFeatureManager::class);
        $value = new FeatureValue('max_clients', FeatureType::INTEGER, 5);
        $manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());

        self::assertTrue($gate->canUse('max_clients', 4));
        self::assertFalse($gate->canUse('max_clients', 5));
    }

    public function testRemainingDelegatesToFeatureValue(): void
    {
        $manager = self::createStub(PlanFeatureManager::class);
        $value = new FeatureValue('max_clients', FeatureType::INTEGER, 5);
        $manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());

        self::assertSame(2, $gate->remaining('max_clients', 3));
    }

    public function testUpgradeOptionsMapsPlansToReferences(): void
    {
        $manager = $this->createMock(PlanFeatureManager::class);
        $plan = new Plan();
        $reflection = new ReflectionClass($plan);

        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($plan, new Ulid());

        $nameProp = $reflection->getProperty('name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($plan, 'Pro');

        $manager->expects(self::once())
            ->method('findPlansWithFeature')
            ->with('custom_branding')
            ->willReturn([$plan]);

        $gate = new PlanFeatureGate($manager, new NullSubscriberResolver());
        $options = $gate->upgradeOptions('custom_branding');

        self::assertFalse($options->isEmpty());
        self::assertCount(1, $options->plans);
        self::assertSame('Pro', $options->plans[0]->name);
        self::assertSame($plan->getId()->toBase58(), $options->plans[0]->id);
    }

    private function subscriber(): SubscribableInterface
    {
        return new class() implements SubscribableInterface {};
    }
}
