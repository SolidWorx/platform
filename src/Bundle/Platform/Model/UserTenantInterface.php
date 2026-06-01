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

namespace SolidWorx\Platform\PlatformBundle\Model;

use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * Contract for the membership entity linking a user to a tenant.
 *
 * Extend {@see UserTenant} (the mapped base) to add your own fields and register the concrete class
 * via `platform.multi_tenancy.models.user_tenant`.
 */
interface UserTenantInterface
{
    public function getId(): Ulid;

    public function getUserId(): Ulid;

    public function getTenant(): TenantInterface;

    public function getCreatedAt(): DateTimeImmutable;
}
