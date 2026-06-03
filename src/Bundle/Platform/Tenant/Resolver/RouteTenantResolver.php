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
use function is_array;
use function is_string;

/**
 * Resolves the tenant from a route parameter (the tenant's ULID id).
 *
 * Lowest priority in the chain and disabled by default; enable it for path-based tenancy. It is
 * safe because membership is validated when the tenant is applied.
 */
#[AutoconfigureTag('platform.tenant_resolver', ['priority' => 10])]
final readonly class RouteTenantResolver implements TenantResolverInterface
{
    public function __construct(
        #[Autowire(param: 'solidworx_platform.multi_tenancy.route_param')]
        private string $routeParam,
    ) {
    }

    public function resolve(Request $request): ?Ulid
    {
        $routeParams = $request->attributes->get('_route_params', []);

        if (! is_array($routeParams)) {
            return null;
        }

        $value = $routeParams[$this->routeParam] ?? null;

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
