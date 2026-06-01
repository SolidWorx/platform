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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

/**
 * Resolves the tenant for an incoming request.
 *
 * Resolvers are arranged in a priority-ordered chain (higher priority is consulted first); the
 * first resolver to return a non-null id wins. Register a custom resolver by tagging it with
 * `platform.tenant_resolver` (use `#[AutoconfigureTag]` with a `priority`).
 */
interface TenantResolverInterface
{
    public function resolve(Request $request): ?Ulid;
}
