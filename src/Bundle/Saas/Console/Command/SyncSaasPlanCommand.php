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

namespace SolidWorx\Platform\SaasBundle\Console\Command;

use Override;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'saas:sync-plan', description: 'Syncs the SaaS plans from an external provider')]
final class SyncSaasPlanCommand extends Command
{
    public function __construct(
        private readonly PaymentIntegrationInterface $paymentIntegration,
        private readonly PlanRepository $planRepository,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function handle(): int
    {
        foreach ($this->paymentIntegration->getPlans() as $planInfo) {
            $plan = $this->planRepository->findOneBy([
                'planId' => $planInfo->id,
            ]) ?? new Plan();

            $plan->setName($planInfo->name);
            $plan->setDescription($planInfo->description);
            $plan->setPrice($planInfo->price);
            $plan->setPlanId($planInfo->id);
            $plan->setInterval($planInfo->interval);

            $this->planRepository->save($plan);
        }

        return self::SUCCESS;
    }
}
