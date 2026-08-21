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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Event;

use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the {@see \SolidWorx\Platform\PlatformBundle\Tenant\TenantContext} commits a
 * tenant change.
 *
 * Listeners read the target tenant from this event (not from the context, which has not yet been
 * mutated). A listener may veto the switch by throwing — the context will not commit and the
 * Doctrine filter will not be enabled.
 */
final class TenantSwitchedEvent extends Event
{
    public function __construct(
        private readonly ?Ulid $previousTenantId,
        private readonly ?Ulid $tenantId,
    ) {
    }

    public function getPreviousTenantId(): ?Ulid
    {
        return $this->previousTenantId;
    }

    public function getTenantId(): ?Ulid
    {
        return $this->tenantId;
    }
}
