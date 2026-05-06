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

namespace SolidWorx\Platform\PlatformBundle\Feature;

interface FeatureGate
{
    /**
     * Resolve the full feature value (enabled state, limit, type, raw value).
     *
     * When $for is null, implementations should resolve the current subject
     * via the injected SubscriberResolver. Implementations are free to fall
     * back to configured defaults when no subject is available.
     */
    public function resolve(string $featureKey, ?SubscribableInterface $for = null): FeatureValue;

    /**
     * Convenience: is the feature enabled for this subject?
     */
    public function isEnabled(string $featureKey, ?SubscribableInterface $for = null): bool;

    /**
     * Convenience: can the subject use one more, given current usage?
     */
    public function canUse(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): bool;

    /**
     * Convenience: how many remaining?
     *
     * Returns null when the feature is unlimited or non-quantitative.
     */
    public function remaining(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): ?int;

    /**
     * Returns upgrade guidance when a feature is unavailable.
     *
     * Empty in non-SaaS deployments; populated when the SaaS implementation
     * is wired and other plans expose the feature.
     */
    public function upgradeOptions(string $featureKey): UpgradeOptions;
}
