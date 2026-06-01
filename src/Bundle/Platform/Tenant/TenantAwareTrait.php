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

namespace SolidWorx\Platform\PlatformBundle\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * Provides the `tenant_id` mapping and accessors for {@see TenantAwareInterface} entities.
 *
 * The column is indexed automatically (and forced to lead any composite index it appears in) by
 * the tenant metadata listener, so consumers only need to `use` this trait.
 */
trait TenantAwareTrait
{
    #[ORM\Column(name: 'tenant_id', type: UlidType::NAME, nullable: true)]
    private ?Ulid $tenantId = null;

    public function getTenantId(): ?Ulid
    {
        return $this->tenantId;
    }

    public function setTenantId(?Ulid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
