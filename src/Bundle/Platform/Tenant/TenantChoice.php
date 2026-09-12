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

use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Component\Uid\Ulid;

/**
 * A tenant a user may enter, as listed on the selection page and in the switcher.
 *
 * Deliberately not a {@see TenantInterface}: listing a user's tenants only ever needs an id and a
 * label, and hydrating full entities for a dropdown is wasted work. It also keeps the listing
 * honest — the query behind it is a partial select.
 */
final readonly class TenantChoice
{
    public function __construct(
        public Ulid $id,
        public string $name,
    ) {
    }

    public static function fromTenant(TenantInterface $tenant): self
    {
        return new self($tenant->getId(), $tenant->getName());
    }
}
