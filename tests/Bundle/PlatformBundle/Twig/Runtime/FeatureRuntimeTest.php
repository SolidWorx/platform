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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Feature\NoopFeatureGate;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\FeatureRuntime;

#[CoversClass(FeatureRuntime::class)]
final class FeatureRuntimeTest extends TestCase
{
    public function testRuntimeBackedByNoopGateAlwaysReportsAvailable(): void
    {
        $runtime = new FeatureRuntime(new NoopFeatureGate());

        self::assertTrue($runtime->isEnabled('any_key'));
        self::assertTrue($runtime->canUse('any_key', 1_000));
        self::assertNull($runtime->remaining('any_key'));
        self::assertTrue($runtime->isUnlimited('any_key'));
        self::assertTrue($runtime->upgradeOptions('any_key')->isEmpty());
    }
}
