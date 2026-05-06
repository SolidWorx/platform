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
use SolidWorx\Platform\PlatformBundle\Feature\PlanReference;
use SolidWorx\Platform\PlatformBundle\Feature\UpgradeOptions;

#[CoversClass(UpgradeOptions::class)]
#[CoversClass(PlanReference::class)]
final class UpgradeOptionsTest extends TestCase
{
    public function testIsEmptyWithNoPlans(): void
    {
        $options = new UpgradeOptions([]);

        self::assertTrue($options->isEmpty());
        self::assertSame([], $options->plans);
    }

    public function testIsNotEmptyWithPlans(): void
    {
        $plan = new PlanReference('01HX', 'Pro');
        $options = new UpgradeOptions([$plan]);

        self::assertFalse($options->isEmpty());
        self::assertCount(1, $options->plans);
        self::assertSame('01HX', $options->plans[0]->id);
        self::assertSame('Pro', $options->plans[0]->name);
    }
}
