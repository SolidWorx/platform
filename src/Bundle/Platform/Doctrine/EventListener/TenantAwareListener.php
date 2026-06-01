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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\EventListener;

use Symfony\Component\Uid\Ulid;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;

/**
 * Stamps newly persisted {@see TenantAwareInterface} entities with the tenant currently in scope,
 * so callers never have to set `tenant_id` by hand.
 *
 * Entities that already carry a tenant are left untouched, and nothing happens when no tenant is in
 * scope (e.g. a cross-tenant batch operation).
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class TenantAwareListener
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $entity instanceof TenantAwareInterface || $entity->getTenantId() instanceof Ulid) {
            return;
        }

        $tenantId = $this->tenantContext->getTenantId();

        if (!$tenantId instanceof Ulid) {
            return;
        }

        $entity->setTenantId($tenantId);
    }
}
