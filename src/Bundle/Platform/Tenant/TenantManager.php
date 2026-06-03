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
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * High-level entry point for working with the tenant in scope and the Doctrine tenant filter.
 *
 * Prefer this over touching {@see TenantContext} and the filter directly: it keeps the context and
 * the filter consistent and provides scoped helpers for cross-tenant work.
 */
final readonly class TenantManager
{
    public function __construct(
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function switchTo(Ulid|TenantInterface $tenant): void
    {
        $this->tenantContext->setTenant($tenant);
    }

    public function clear(): void
    {
        $this->tenantContext->clear();
    }

    public function isFilterEnabled(): bool
    {
        return $this->entityManager->getFilters()->isEnabled(TenantFilter::NAME);
    }

    public function enableFilter(): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (!$tenantId instanceof Ulid) {
            return;
        }

        $this->entityManager->getFilters()
            ->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $tenantId, UlidType::NAME);
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
    public function runAs(Ulid|TenantInterface $tenant, callable $callback): mixed
    {
        $this->tenantContext->push($tenant);

        try {
            return $callback();
        } finally {
            $this->tenantContext->pop();
        }
    }
}
