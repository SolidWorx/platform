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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Scope;

/**
 * What the scope guard should do about the tenant scope of the current request.
 *
 * @see TenantScopeResolver
 */
enum TenantScopeOutcome
{
    /**
     * A tenant is already in scope; the request proceeds untouched.
     */
    case AlreadyScoped;

    /**
     * The user belongs to exactly one tenant and it has been entered; the request proceeds.
     */
    case AutoSelected;

    /**
     * The user belongs to several tenants and has to pick one.
     */
    case NeedsSelection;

    /**
     * The user belongs to no tenant and may create their first.
     */
    case NeedsOnboarding;

    /**
     * The user belongs to no tenant and onboarding is disabled — nothing they can do.
     */
    case NoAccess;

    /**
     * Whether the request can continue, as opposed to being interrupted with a redirect or an
     * error.
     */
    public function allowsRequest(): bool
    {
        return match ($this) {
            self::AlreadyScoped, self::AutoSelected => true,
            default => false,
        };
    }
}
