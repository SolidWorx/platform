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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Onboarding;

use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;

/**
 * Turns a filled-in onboarding form into a usable workspace.
 *
 * Everything that has to happen after a valid submission lives behind this interface — persisting
 * the tenant, granting the creator membership, entering the tenant — so an application can decorate
 * or replace it to seed default data without reimplementing the controller or the form.
 *
 * @see DefaultTenantOnboarder
 */
interface TenantOnboarder
{
    /**
     * Persists the tenant, makes the user a member, and puts the tenant in scope for the rest of
     * the request.
     */
    public function onboard(TenantInterface $tenant, UserInterface $user): void;
}
