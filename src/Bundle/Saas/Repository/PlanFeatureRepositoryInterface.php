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

use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;

/**
 * Interface for PlanFeature repository operations.
 */
interface PlanFeatureRepositoryInterface
{
    /**
     * @return array<PlanFeature>
     */
    public function findByPlan(Plan $plan): array;

    public function findOneByPlanAndKey(Plan $plan, string $featureKey): ?PlanFeature;

    /**
     * @param array<Plan> $plans
     * @return array<int, PlanFeature>
     */
    public function findByPlans(array $plans): array;

    /**
     * @return array<PlanFeature>
     */
    public function findByFeatureKey(string $featureKey): array;

    public function save(PlanFeature $planFeature, bool $flush = true): void;

    public function remove(PlanFeature $planFeature, bool $flush = true): void;
}
