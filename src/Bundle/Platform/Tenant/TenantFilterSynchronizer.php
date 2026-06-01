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
use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps the Doctrine {@see TenantFilter} in sync with the tenant in scope.
 *
 * Runs at a lower priority than the access-validation listener, so the filter is never enabled for
 * a switch that was vetoed. Reads the target tenant from the event payload rather than the context,
 * because the context only commits after the event has been dispatched.
 */
#[AsEventListener(event: TenantSwitchedEvent::class, priority: 0)]
final readonly class TenantFilterSynchronizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(TenantSwitchedEvent $event): void
    {
        $filters = $this->entityManager->getFilters();
        $tenantId = $event->getTenantId();

        if (!$tenantId instanceof Ulid) {
            if ($filters->isEnabled(TenantFilter::NAME)) {
                $filters->disable(TenantFilter::NAME);
            }

            return;
        }

        $filters->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $tenantId, UlidType::NAME);
    }
}
