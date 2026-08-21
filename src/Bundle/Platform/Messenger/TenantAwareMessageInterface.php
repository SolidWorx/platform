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

namespace SolidWorx\Platform\PlatformBundle\Messenger;

use Symfony\Component\Uid\Ulid;

/**
 * Opt-in contract for messages that should carry the originating tenant in their own payload.
 *
 * The {@see TenantMiddleware} stamps the tenant on dispatch and restores it on handling; messages
 * implementing this interface additionally persist the tenant in the message body. Use together
 * with {@see TenantAwareMessageTrait}.
 */
interface TenantAwareMessageInterface
{
    public function getTenantId(): ?Ulid;

    public function setTenantId(?Ulid $tenantId): void;
}
