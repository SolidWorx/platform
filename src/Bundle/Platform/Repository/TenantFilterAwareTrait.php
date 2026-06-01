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

namespace SolidWorx\Platform\PlatformBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;

/**
 * Repository helper to run a query with the tenant filter temporarily suspended.
 *
 * The filter is suspended (preserving its bound tenant) and restored afterwards, so a deliberate
 * cross-tenant query does not leak the disabled state to later queries.
 */
trait TenantFilterAwareTrait
{
    abstract protected function getEntityManager(): EntityManagerInterface;

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function withoutTenantFilter(callable $callback): mixed
    {
        $filters = $this->getEntityManager()->getFilters();

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
}
