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
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;

/**
 * @template-extends EntityRepository<PlanFeature>
 */
class PlanFeatureRepository extends EntityRepository implements PlanFeatureRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanFeature::class);
    }

    /**
     * @return array<PlanFeature>
     */
    #[Override]
    public function findByPlan(Plan $plan): array
    {
        return $this->findBy([
            'plan' => $plan,
        ]);
    }

    #[Override]
    public function findOneByPlanAndKey(Plan $plan, string $featureKey): ?PlanFeature
    {
        return $this->findOneBy([
            'plan' => $plan,
            'featureKey' => $featureKey,
        ]);
    }

    /**
     * @param array<Plan> $plans
     * @return array<int, PlanFeature>
     */
    #[Override]
    public function findByPlans(array $plans): array
    {
        if ($plans === []) {
            return [];
        }

        /** @var array<int, PlanFeature> $result */
        $result = $this->createQueryBuilder('pf')
            ->where('pf.plan IN (:plans)')
            ->setParameter('plans', $plans)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find all plans that have a specific feature defined.
     *
     * @return array<PlanFeature>
     */
    #[Override]
    public function findByFeatureKey(string $featureKey): array
    {
        return $this->findBy([
            'featureKey' => $featureKey,
        ]);
    }
}
