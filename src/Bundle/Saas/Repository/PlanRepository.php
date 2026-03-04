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

namespace SolidWorx\Platform\SaasBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Override;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use Symfony\Component\Uid\Ulid;

/**
 * @template-extends EntityRepository<Plan>
 */
final class PlanRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    /**
     * @param string|Plan|Ulid $id
     */
    #[Override]
    public function find($id, $lockMode = null, $lockVersion = null): ?Plan
    {
        return match (get_debug_type($id)) {
            Plan::class => $id,
            'string' => $this->findOneBy([
                'planId' => $id,
            ]),
            default => parent::find($id, $lockMode, $lockVersion),
        };
    }
}
