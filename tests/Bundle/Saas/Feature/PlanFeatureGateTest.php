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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureType;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;
use SolidWorx\Platform\PlatformBundle\Feature\NullSubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureGate;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use Symfony\Component\Uid\Ulid;

#[CoversClass(PlanFeatureGate::class)]
final class PlanFeatureGateTest extends TestCase
{
    /**
     * @var PlanFeatureManager&MockObject
     */
    private PlanFeatureManager $manager;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(PlanFeatureManager::class);
    }

    public function testResolveWithExplicitSubscriberDelegatesToManager(): void
    {
        $subscriber = $this->subscriber();
        $expected = new FeatureValue('max_clients', FeatureType::INTEGER, 50);

        $this->manager->expects(self::once())
            ->method('getFeatureForSubscriber')
            ->with($subscriber, 'max_clients')
            ->willReturn($expected);

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());

        self::assertSame($expected, $gate->resolve('max_clients', $subscriber));
    }

    public function testResolveWithoutSubscriberFallsBackToConfigDefault(): void
    {
        $expected = new FeatureValue('max_clients', FeatureType::INTEGER, 5);

        $this->manager->expects(self::once())
            ->method('getConfigDefault')
            ->with('max_clients')
            ->willReturn($expected);

        $this->manager->expects(self::never())->method('getFeatureForSubscriber');

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());

        self::assertSame($expected, $gate->resolve('max_clients'));
    }

    public function testResolveUsesResolverWhenSubscriberOmitted(): void
    {
        $subscriber = $this->subscriber();
        $expected = new FeatureValue('custom_branding', FeatureType::BOOLEAN, true);

        $resolver = $this->createMock(SubscriberResolver::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($subscriber);

        $this->manager->expects(self::once())
            ->method('getFeatureForSubscriber')
            ->with($subscriber, 'custom_branding')
            ->willReturn($expected);

        $gate = new PlanFeatureGate($this->manager, $resolver);

        self::assertSame($expected, $gate->resolve('custom_branding'));
    }

    public function testIsEnabledDelegatesToFeatureValue(): void
    {
        $value = new FeatureValue('flag', FeatureType::BOOLEAN, true);
        $this->manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());

        self::assertTrue($gate->isEnabled('flag'));
    }

    public function testCanUseDelegatesToFeatureValueAllows(): void
    {
        $value = new FeatureValue('max_clients', FeatureType::INTEGER, 5);
        $this->manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());

        self::assertTrue($gate->canUse('max_clients', 4));
        self::assertFalse($gate->canUse('max_clients', 5));
    }

    public function testRemainingDelegatesToFeatureValue(): void
    {
        $value = new FeatureValue('max_clients', FeatureType::INTEGER, 5);
        $this->manager->method('getConfigDefault')->willReturn($value);

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());

        self::assertSame(2, $gate->remaining('max_clients', 3));
    }

    public function testUpgradeOptionsMapsPlansToReferences(): void
    {
        $plan = new Plan();
        $reflection = new ReflectionClass($plan);

        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($plan, new Ulid());

        $nameProp = $reflection->getProperty('name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($plan, 'Pro');

        $this->manager->expects(self::once())
            ->method('findPlansWithFeature')
            ->with('custom_branding')
            ->willReturn([$plan]);

        $gate = new PlanFeatureGate($this->manager, new NullSubscriberResolver());
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
