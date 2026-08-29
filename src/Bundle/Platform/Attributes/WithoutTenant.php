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

namespace SolidWorx\Platform\PlatformBundle\Attributes;

use Attribute;

/**
 * Exempts a controller from the tenant scope guard.
 *
 * With `platform.multi_tenancy.require_tenant` enabled, an authenticated user without a tenant in
 * scope is redirected to the selection or onboarding page. Pages that must work outside a tenant —
 * account settings, billing, an admin console, the selection page itself — carry this attribute.
 *
 * Applied to a class it exempts every action on it; applied to a method it exempts that action
 * only.
 *
 * @see \SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeGuardListener
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class WithoutTenant
{
}
