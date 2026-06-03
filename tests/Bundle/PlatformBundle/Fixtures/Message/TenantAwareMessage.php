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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Message;

use SolidWorx\Platform\PlatformBundle\Messenger\TenantAwareMessageInterface;
use SolidWorx\Platform\PlatformBundle\Messenger\TenantAwareMessageTrait;

/**
 * Test message that carries the tenant in its own payload.
 */
final class TenantAwareMessage implements TenantAwareMessageInterface
{
    use TenantAwareMessageTrait;
}
