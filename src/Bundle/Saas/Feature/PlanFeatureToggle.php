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
use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;

/**
 * Default implementation of FeatureToggleInterface using PlanFeatureManager.
 *
 * This provides a simple feature toggle API that can be used standalone or
 * as a bridge to external libraries like SolidWorx/Toggler.
 */
final readonly class PlanFeatureToggle implements FeatureToggleInterface
{
    public function __construct(
        private PlanFeatureManager $planFeatureManager,
    ) {
    }

    #[Override]
    public function isActive(string $featureKey, SubscribableInterface $subscriber): bool
    {
        return $this->planFeatureManager->hasFeatureForSubscriber($subscriber, $featureKey);
    }

    /**
     * @return int|bool|string|array<mixed>
     */
    #[Override]
    public function getValue(string $featureKey, SubscribableInterface $subscriber): int|bool|string|array
    {
        try {
            return $this->planFeatureManager->getFeatureForSubscriber($subscriber, $featureKey)->value;
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    #[Override]
    public function hasFeature(string $featureKey): bool
    {
        return isset($this->planFeatureManager->getAvailableFeatures()[$featureKey]);
    }
}
