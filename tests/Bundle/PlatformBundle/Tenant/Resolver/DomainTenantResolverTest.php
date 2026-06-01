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

use Symfony\Component\Uid\Ulid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\DomainTenantResolver;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(DomainTenantResolver::class)]
final class DomainTenantResolverTest extends TestCase
{
    public function testResolvesTenantByHost(): void
    {
        $tenant = new Tenant('Acme');

        $repository = self::createStub(TenantRepository::class);
        $repository->method('findOneByDomain')->willReturn($tenant);

        $resolver = new DomainTenantResolver($repository);

        $request = Request::create('https://acme.example.com/dashboard');

        $this->assertSame($tenant->getId()->toRfc4122(), $resolver->resolve($request)?->toRfc4122());
    }

    public function testReturnsNullForUnknownHost(): void
    {
        $repository = self::createStub(TenantRepository::class);
        $repository->method('findOneByDomain')->willReturn(null);

        $resolver = new DomainTenantResolver($repository);

        $this->assertNotInstanceOf(Ulid::class, $resolver->resolve(Request::create('https://unknown.example.com/')));
    }
}
