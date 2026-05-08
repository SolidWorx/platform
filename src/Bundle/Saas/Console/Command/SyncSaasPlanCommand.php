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
use SolidWorx\Platform\SaasBundle\Entity\PlanPrice;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use SolidWorx\Platform\SaasBundle\Repository\PlanPriceRepository;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'saas:sync-plan', description: 'Syncs the SaaS plans from an external provider')]
final class SyncSaasPlanCommand extends Command
{
    public function __construct(
        private readonly PaymentIntegrationInterface $paymentIntegration,
        private readonly PlanRepository $planRepository,
        private readonly PlanPriceRepository $planPriceRepository,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function handle(): int
    {
        $seenVariantIds = [];

        foreach ($this->paymentIntegration->getPlans() as $product) {
            $plan = $this->planRepository->findOneBy([
                'planId' => $product->id,
            ]) ?? new Plan();

            $plan->setName($product->name);
            $plan->setDescription($product->description);
            $plan->setPlanId($product->id);

            foreach ($product->prices as $priceDto) {
                $price = $this->planPriceRepository->findOneBy([
                    'variantId' => $priceDto->variantId,
                ]) ?? new PlanPrice();

                $price->setVariantId($priceDto->variantId);
                $price->setPrice($priceDto->price);
                $price->setInterval($priceDto->interval);
                $price->setActive(true);

                $plan->addPrice($price);

                $seenVariantIds[] = $priceDto->variantId;
            }

            $this->planRepository->save($plan);
        }

        $this->deactivateStalePrices($seenVariantIds);

        return self::SUCCESS;
    }

    /**
     * @param list<string> $seenVariantIds
     */
    private function deactivateStalePrices(array $seenVariantIds): void
    {
        $existingPrices = $this->planPriceRepository->findAll();

        foreach ($existingPrices as $price) {
            // Preserve the local-only free price sentinel and any active price
            // we just synced. Variant ids no longer in the provider response
            // are flipped inactive so historical Subscription FKs stay valid.
            if ($price->isFree()) {
                continue;
            }
            if (in_array($price->getVariantId(), $seenVariantIds, true)) {
                continue;
            }
            if ($price->isActive()) {
                $price->setActive(false);
                $this->planPriceRepository->save($price);
            }
        }
    }
}
