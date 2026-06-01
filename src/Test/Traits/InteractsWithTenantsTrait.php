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

namespace SolidWorx\Platform\Test\Traits;

use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

/**
 * Test helpers for working with tenants and the tenant in scope.
 */
trait InteractsWithTenantsTrait
{
    protected function createTenantContext(): TenantContext
    {
        return new TenantContext(new EventDispatcher());
    }

    protected function createTenant(string $name = 'Test Tenant', ?Ulid $createdById = null): Tenant
    {
        return new Tenant($name, $createdById);
    }

    protected function setCurrentTenant(TenantContext $tenantContext, Ulid|Tenant $tenant): void
    {
        $tenantContext->setTenant($tenant);
    }

    protected function clearCurrentTenant(TenantContext $tenantContext): void
    {
        $tenantContext->clear();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function runAsTenant(TenantManager $tenantManager, Ulid|Tenant $tenant, callable $callback): mixed
    {
        return $tenantManager->runAs($tenant, $callback);
    }
}
