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

use Override;

/**
 * Default FeatureGate used in non-SaaS deployments.
 *
 * Reports every feature as available with no quantitative limit. Never consults
 * a registry — self-hosted has no opinion about which feature keys exist.
 */
final readonly class NoopFeatureGate implements FeatureGate
{
    #[Override]
    public function resolve(string $featureKey, ?SubscribableInterface $for = null): FeatureValue
    {
        return new FeatureValue($featureKey, FeatureType::INTEGER, FeatureValue::UNLIMITED);
    }

    #[Override]
    public function isEnabled(string $featureKey, ?SubscribableInterface $for = null): bool
    {
        return true;
    }

    #[Override]
    public function canUse(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): bool
    {
        return true;
    }

    #[Override]
    public function remaining(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): ?int
    {
        return null;
    }

    #[Override]
    public function upgradeOptions(string $featureKey): UpgradeOptions
    {
        return new UpgradeOptions([]);
    }
}
