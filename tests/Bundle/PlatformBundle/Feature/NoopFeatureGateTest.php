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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureType;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;
use SolidWorx\Platform\PlatformBundle\Feature\NoopFeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;

#[CoversClass(NoopFeatureGate::class)]
final class NoopFeatureGateTest extends TestCase
{
    public function testResolveReturnsUnlimitedFeatureValue(): void
    {
        $gate = new NoopFeatureGate();

        $value = $gate->resolve('any_key');

        self::assertSame('any_key', $value->key);
        self::assertSame(FeatureType::INTEGER, $value->type);
        self::assertSame(FeatureValue::UNLIMITED, $value->value);
        self::assertTrue($value->isUnlimited());
    }

    public function testIsEnabledAlwaysTrue(): void
    {
        $gate = new NoopFeatureGate();

        self::assertTrue($gate->isEnabled('any_key'));
        self::assertTrue($gate->isEnabled('any_key', $this->subscriber()));
    }

    public function testCanUseAlwaysTrueRegardlessOfUsage(): void
    {
        $gate = new NoopFeatureGate();

        self::assertTrue($gate->canUse('any_key'));
        self::assertTrue($gate->canUse('any_key', 999_999));
        self::assertTrue($gate->canUse('any_key', 999_999, $this->subscriber()));
    }

    public function testRemainingAlwaysNullForUnlimited(): void
    {
        $gate = new NoopFeatureGate();

        self::assertNull($gate->remaining('any_key'));
        self::assertNull($gate->remaining('any_key', 100));
    }

    public function testUpgradeOptionsAlwaysEmpty(): void
    {
        $gate = new NoopFeatureGate();

        $options = $gate->upgradeOptions('any_key');

        self::assertTrue($options->isEmpty());
    }

    private function subscriber(): SubscribableInterface
    {
        return new class() implements SubscribableInterface {};
    }
}
