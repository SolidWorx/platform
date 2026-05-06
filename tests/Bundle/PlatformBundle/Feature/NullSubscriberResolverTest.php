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
use SolidWorx\Platform\PlatformBundle\Feature\NullSubscriberResolver;

#[CoversClass(NullSubscriberResolver::class)]
final class NullSubscriberResolverTest extends TestCase
{
    public function testResolveAlwaysReturnsNull(): void
    {
        $resolver = new NullSubscriberResolver();

        self::assertNull($resolver->resolve());
    }
}
