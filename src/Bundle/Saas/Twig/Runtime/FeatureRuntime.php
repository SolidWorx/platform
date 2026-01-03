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

namespace SolidWorx\Platform\SaasBundle\Twig\Runtime;

use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for plan feature functions.
 *
 * Uses lazy loading to avoid instantiating the PlanFeatureManager
 * unless feature functions are actually called in templates.
 */
final readonly class FeatureRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private PlanFeatureManager $featureManager,
    ) {
    }

    /**
     * Check if a subscriber has a feature enabled.
     *
     * Usage: {% if has_feature(app.user, 'api_access') %}
     */
    public function hasFeature(SubscribableInterface $subscriber, string $featureKey): bool
    {
        return $this->featureManager->hasFeatureForSubscriber($subscriber, $featureKey);
    }

    /**
     * Get the feature value for a subscriber.
     *
     * Usage: {{ feature_value(app.user, 'max_users') }}
     *
     * @return int|bool|string|array<mixed>
     */
    public function getFeatureValue(SubscribableInterface $subscriber, string $featureKey): int|bool|string|array
    {
        try {
            return $this->featureManager->getFeatureForSubscriber($subscriber, $featureKey)->value;
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    /**
     * Check if a subscriber can use a feature at the given usage level.
     *
     * Usage: {% if can_use_feature(app.user, 'max_users', current_count) %}
     */
    public function canUseFeature(SubscribableInterface $subscriber, string $featureKey, int $currentUsage = 0): bool
    {
        return $this->featureManager->canUseForSubscriber($subscriber, $featureKey, $currentUsage);
    }

    /**
     * Get the remaining quota for a feature.
     *
     * Usage: {{ feature_remaining(app.user, 'max_users', current_count) }}
     *
     * Returns null for unlimited features or non-integer features.
     */
    public function getRemainingQuota(SubscribableInterface $subscriber, string $featureKey, int $currentUsage = 0): ?int
    {
        try {
            return $this->featureManager->getFeatureForSubscriber($subscriber, $featureKey)->getRemainingQuota($currentUsage);
        } catch (UndefinedFeatureException) {
            return null;
        }
    }

    /**
     * Check if a feature is unlimited for a subscriber.
     *
     * Usage: {% if is_feature_unlimited(app.user, 'max_users') %}Unlimited{% endif %}
     */
    public function isUnlimited(SubscribableInterface $subscriber, string $featureKey): bool
    {
        try {
            return $this->featureManager->getFeatureForSubscriber($subscriber, $featureKey)->isUnlimited();
        } catch (UndefinedFeatureException) {
            return false;
        }
    }
}
