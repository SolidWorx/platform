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
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\SessionTenantResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Ulid;

#[CoversClass(SessionTenantResolver::class)]
final class SessionTenantResolverTest extends TestCase
{
    public function testResolvesTenantFromSession(): void
    {
        $tenantId = new Ulid();
        $request = $this->requestWithSession('_tenant_id', $tenantId->toRfc4122());

        $resolver = new SessionTenantResolver('_tenant_id');

        $this->assertSame($tenantId->toRfc4122(), $resolver->resolve($request)?->toRfc4122());
    }

    public function testReturnsNullWhenNoSession(): void
    {
        $resolver = new SessionTenantResolver('_tenant_id');

        $this->assertNotInstanceOf(Ulid::class, $resolver->resolve(Request::create('/')));
    }

    public function testReturnsNullForInvalidValue(): void
    {
        $request = $this->requestWithSession('_tenant_id', 'not-a-ulid');

        $resolver = new SessionTenantResolver('_tenant_id');

        $this->assertNotInstanceOf(Ulid::class, $resolver->resolve($request));
    }

    private function requestWithSession(string $key, string $value): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set($key, $value);

        $request = Request::create('/');
        $request->setSession($session);

        return $request;
    }
}
