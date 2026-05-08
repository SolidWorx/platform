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

namespace SolidWorx\Platform\SaasBundle\Feature;

use Override;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;
use SolidWorx\Platform\PlatformBundle\Feature\PlanReference;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\UpgradeOptions;
use SolidWorx\Platform\SaasBundle\Entity\Plan;

final readonly class PlanFeatureGate implements FeatureGate
{
    public function __construct(
        private PlanFeatureManager $manager,
        private SubscriberResolver $resolver,
    ) {
    }

    #[Override]
    public function resolve(string $featureKey, ?SubscribableInterface $for = null): FeatureValue
    {
        $for ??= $this->resolver->resolve();

        return $for instanceof SubscribableInterface
            ? $this->manager->getFeatureForSubscriber($for, $featureKey)
            : $this->manager->getConfigDefault($featureKey);
    }

    #[Override]
    public function isEnabled(string $featureKey, ?SubscribableInterface $for = null): bool
    {
        return $this->resolve($featureKey, $for)->isEnabled();
    }

    #[Override]
    public function canUse(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): bool
    {
        return $this->resolve($featureKey, $for)->allows($currentUsage);
    }

    #[Override]
    public function remaining(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): ?int
    {
        return $this->resolve($featureKey, $for)->getRemainingQuota($currentUsage);
    }

    #[Override]
    public function upgradeOptions(string $featureKey): UpgradeOptions
    {
        $references = array_map(
            static fn (Plan $plan): PlanReference => new PlanReference(
                $plan->getId()->toBase58(),
                $plan->getName(),
            ),
            $this->manager->findPlansWithFeature($featureKey),
        );

        return new UpgradeOptions($references);
    }
}
