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

namespace SolidWorx\Platform\PlatformBundle\Messenger;

use Symfony\Component\Uid\Ulid;

/**
 * Default implementation of {@see TenantAwareMessageInterface}.
 *
 * The tenant id is stored as an RFC 4122 string so the message stays portable across any transport
 * serializer.
 */
trait TenantAwareMessageTrait
{
    private ?string $tenantId = null;

    public function getTenantId(): ?Ulid
    {
        return $this->tenantId !== null ? Ulid::fromString($this->tenantId) : null;
    }

    public function setTenantId(?Ulid $tenantId): void
    {
        $this->tenantId = $tenantId?->toRfc4122();
    }
}
