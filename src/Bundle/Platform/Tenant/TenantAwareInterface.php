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

use Symfony\Component\Uid\Ulid;

/**
 * Implemented by entities whose rows are scoped to a tenant.
 *
 * Use together with {@see TenantAwareTrait} for the default mapping and accessors.
 */
interface TenantAwareInterface
{
    public function getTenantId(): ?Ulid;

    public function setTenantId(?Ulid $tenantId): void;
}
