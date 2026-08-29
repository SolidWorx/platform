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

use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a tenant has been created through onboarding, with the creator's membership
 * already persisted and the tenant in scope.
 *
 * Listen to it to seed whatever a brand-new workspace needs — default roles, sample data, a trial
 * subscription. Because the tenant is already in scope, tenant-aware entities created by a listener
 * are attributed to it automatically.
 */
final class TenantCreatedEvent extends Event
{
    public function __construct(
        private readonly TenantInterface $tenant,
        private readonly UserInterface $creator,
    ) {
    }

    public function getTenant(): TenantInterface
    {
        return $this->tenant;
    }

    public function getCreator(): UserInterface
    {
        return $this->creator;
    }
}
