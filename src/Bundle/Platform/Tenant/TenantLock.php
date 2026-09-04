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

use Override;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records that the tenant in scope was fixed by the infrastructure and may not be changed.
 *
 * Set when the resolver chain is won by a {@see Resolver\LockingTenantResolverInterface} — in
 * practice, a request arriving on a tenant's custom domain. The host is an unambiguous statement of
 * which tenant the request belongs to, so on such a request there is no meaningful "switch tenant"
 * action: the selection page is forbidden and the switcher renders nothing.
 *
 * Request-scoped and reset between kernel invocations, like {@see TenantContext}.
 */
final class TenantLock implements ResetInterface
{
    private ?Ulid $tenantId = null;

    public function lock(Ulid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function isLocked(): bool
    {
        return $this->tenantId instanceof Ulid;
    }

    public function getTenantId(): ?Ulid
    {
        return $this->tenantId;
    }

    /**
     * Whether switching to the given tenant is forbidden. Re-applying the locked tenant is always
     * allowed — it is a no-op, not a switch.
     */
    public function forbidsSwitchTo(?Ulid $tenantId): bool
    {
        if (! $this->tenantId instanceof Ulid) {
            return false;
        }

        return ! $tenantId instanceof Ulid || ! $this->tenantId->equals($tenantId);
    }

    #[Override]
    public function reset(): void
    {
        $this->tenantId = null;
    }
}
