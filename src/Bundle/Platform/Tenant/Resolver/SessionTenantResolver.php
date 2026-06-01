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

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;
use function is_string;

/**
 * Resolves the tenant from the user's session.
 *
 * This is the default for an authenticated user once they have selected a tenant. Membership is not
 * checked here — the access-validation listener enforces it uniformly when the tenant is applied.
 */
#[AutoconfigureTag('platform.tenant_resolver', ['priority' => 20])]
final readonly class SessionTenantResolver implements TenantResolverInterface
{
    public function __construct(
        #[Autowire(param: 'solidworx_platform.multi_tenancy.session_key')]
        private string $sessionKey,
    ) {
    }

    public function resolve(Request $request): ?Ulid
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->getSession()->get($this->sessionKey);

        if (! is_string($value)) {
            return null;
        }

        try {
            return Ulid::fromString($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
