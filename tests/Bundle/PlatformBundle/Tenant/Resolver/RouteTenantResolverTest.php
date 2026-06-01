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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\RouteTenantResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

#[CoversClass(RouteTenantResolver::class)]
final class RouteTenantResolverTest extends TestCase
{
    public function testResolvesTenantFromRouteParam(): void
    {
        $tenantId = new Ulid();
        $request = Request::create('/');
        $request->attributes->set('_route_params', [
            'tenant' => $tenantId->toRfc4122(),
        ]);

        $resolver = new RouteTenantResolver('tenant');

        $this->assertSame($tenantId->toRfc4122(), $resolver->resolve($request)?->toRfc4122());
    }

    public function testReturnsNullWhenParamMissing(): void
    {
        $resolver = new RouteTenantResolver('tenant');

        $this->assertNotInstanceOf(Ulid::class, $resolver->resolve(Request::create('/')));
    }
}
