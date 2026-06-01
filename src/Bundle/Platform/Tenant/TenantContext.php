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
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use function array_pop;

/**
 * Holds the tenant currently in scope for the running request, command or message.
 *
 * The context is request/worker scoped and is reset between kernel invocations via
 * {@see ResetInterface}. Switching tenants dispatches a {@see TenantSwitchedEvent} *before*
 * committing, allowing listeners to veto the change (e.g. access validation) before any state —
 * the context itself or the Doctrine filter — is mutated.
 */
final class TenantContext implements ResetInterface
{
    private ?Ulid $tenantId = null;

    /**
     * @var list<Ulid|null>
     */
    private array $stack = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function setTenant(Ulid|TenantInterface|null $tenant): void
    {
        $new = $this->normalize($tenant);

        if ($this->isSame($new, $this->tenantId)) {
            return;
        }

        // Dispatch before committing so a listener can veto the switch by throwing.
        $this->eventDispatcher->dispatch(new TenantSwitchedEvent($this->tenantId, $new));

        $this->tenantId = $new;
    }

    public function getTenantId(): ?Ulid
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId instanceof Ulid;
    }

    public function clear(): void
    {
        $this->setTenant(null);
    }

    /**
     * Sets a tenant while remembering the previous one, so it can be restored with {@see pop()}.
     */
    public function push(Ulid|TenantInterface|null $tenant): void
    {
        $this->stack[] = $this->tenantId;
        $this->setTenant($tenant);
    }

    /**
     * Restores the tenant remembered by the matching {@see push()} call.
     */
    public function pop(): void
    {
        if ($this->stack === []) {
            $this->setTenant(null);

            return;
        }

        $this->setTenant(array_pop($this->stack));
    }

    #[Override]
    public function reset(): void
    {
        $this->stack = [];
        $this->setTenant(null);
    }

    private function normalize(Ulid|TenantInterface|null $tenant): ?Ulid
    {
        if ($tenant instanceof TenantInterface) {
            return $tenant->getId();
        }

        return $tenant;
    }

    private function isSame(?Ulid $a, ?Ulid $b): bool
    {
        if (! $a instanceof Ulid || ! $b instanceof Ulid) {
            return $a === $b;
        }

        return $a->equals($b);
    }
}
