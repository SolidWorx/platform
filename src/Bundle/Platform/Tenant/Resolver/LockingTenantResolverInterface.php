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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Resolver;

/**
 * Marks a resolver whose result fixes the tenant for the whole request.
 *
 * When such a resolver wins the chain, {@see \SolidWorx\Platform\PlatformBundle\Tenant\TenantLock}
 * is engaged: the tenant cannot be switched, the selection page returns 403 and the switcher
 * renders nothing.
 *
 * Implemented by {@see DomainTenantResolver}, where the request host is an infrastructure-level
 * statement of tenancy that a user must not be able to override. Implement it on your own resolver
 * when its signal is equally authoritative.
 */
interface LockingTenantResolverInterface extends TenantResolverInterface
{
}
