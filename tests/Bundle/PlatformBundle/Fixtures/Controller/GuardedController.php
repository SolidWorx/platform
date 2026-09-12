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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Controller;

use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;

/**
 * An ordinary controller: subject to the tenant scope guard, except on the one action that opts
 * out.
 */
final class GuardedController
{
    public function __invoke(): void
    {
    }

    #[WithoutTenant]
    public function exemptAction(): void
    {
    }
}
