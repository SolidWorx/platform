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
class PlanRepository extends EntityRepository implements PlanRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    /**
     * @param string|Plan|Ulid $id
     */
    #[Override]
    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?Plan
    {
        return match (get_debug_type($id)) {
            Plan::class => $id,
            'string' => $this->findOneBy([
                'planId' => $id,
            ]),
            default => parent::find($id, $lockMode, $lockVersion),
        };
    }

    /**
     * Returns the default active plan, or the cheapest active plan when none
     * is explicitly flagged. Used during signup and when more than one plan
     * is configured to highlight a recommended option.
     */
    public function findDefault(): ?Plan
    {
        $default = $this->createQueryBuilder('p')
            ->where('p.default = :default')
            ->andWhere('p.active = :active')
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->orderBy('p.price', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($default instanceof Plan) {
            return $default;
        }

        return $this->createQueryBuilder('p')
            ->where('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.price', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Plan>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.price', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
