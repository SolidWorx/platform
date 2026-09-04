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

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Exception\TenantLockedException;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use function sprintf;

/**
 * High-level entry point for working with the tenant in scope and the Doctrine tenant filter.
 *
 * Prefer this over touching {@see TenantContext} and the filter directly: it keeps the context and
 * the filter consistent, enforces the request's tenant lock, and provides scoped helpers for
 * cross-tenant work.
 *
 * {@see runAs()} and {@see runWithoutFilter()} are deliberately exempt from the lock. They are
 * bounded, self-restoring scopes for deliberate cross-tenant work — a nightly report running on a
 * request that happens to arrive via a custom domain must still work. The lock exists to stop a
 * *user* changing tenants, which is what {@see switchTo()} and {@see clear()} express.
 */
final readonly class TenantManager
{
    public function __construct(
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
        private TenantLock $tenantLock,
    ) {
    }

    /**
     * @throws TenantLockedException       when the tenant is locked to the request (custom domain)
     *                                     and a different tenant is requested
     * @throws TenantAccessDeniedException when the authenticated user is not a member of the
     *                                     tenant — raised by the access-validation listener while
     *                                     the switch event is dispatched
     */
    public function switchTo(Ulid | TenantInterface $tenant): void
    {
        $tenantId = $tenant instanceof TenantInterface ? $tenant->getId() : $tenant;

        $this->assertNotLocked($tenantId);

        $this->tenantContext->setTenant($tenantId);
    }

    /**
     * @throws TenantLockedException when the tenant is locked to the request
     */
    public function clear(): void
    {
        $this->assertNotLocked(null);

        $this->tenantContext->clear();
    }

    public function isLocked(): bool
    {
        return $this->tenantLock->isLocked();
    }

    public function isFilterEnabled(): bool
    {
        return $this->entityManager->getFilters()->isEnabled(TenantFilter::NAME);
    }

    public function enableFilter(): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (! $tenantId instanceof Ulid) {
            return;
        }

        $this->entityManager->getFilters()
            ->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $tenantId->toBinary(), UlidType::NAME);
    }

    public function disableFilter(): void
    {
        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled(TenantFilter::NAME)) {
            $filters->disable(TenantFilter::NAME);
        }
    }

    /**
     * Runs the callback with the tenant filter suspended, restoring it (with its bound tenant)
     * afterwards. Use for deliberate cross-tenant reads.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runWithoutFilter(callable $callback): mixed
    {
        $filters = $this->entityManager->getFilters();

        if (! $filters->isEnabled(TenantFilter::NAME)) {
            return $callback();
        }

        $filters->suspend(TenantFilter::NAME);

        try {
            return $callback();
        } finally {
            $filters->restore(TenantFilter::NAME);
        }
    }

    /**
     * Runs the callback with the given tenant in scope, restoring the previous tenant afterwards.
     * Use for per-tenant iteration in commands and workers.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runAs(Ulid | TenantInterface $tenant, callable $callback): mixed
    {
        $this->tenantContext->push($tenant);

        try {
            return $callback();
        } finally {
            $this->tenantContext->pop();
        }
    }

    private function assertNotLocked(?Ulid $tenantId): void
    {
        if (! $this->tenantLock->forbidsSwitchTo($tenantId)) {
            return;
        }

        throw new TenantLockedException(sprintf(
            'The tenant is locked to "%s" for this request and cannot be switched.',
            $this->tenantLock->getTenantId()?->toRfc4122() ?? '',
        ));
    }
}
