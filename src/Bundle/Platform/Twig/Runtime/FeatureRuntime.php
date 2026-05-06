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

namespace SolidWorx\Platform\PlatformBundle\Twig\Runtime;

use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\PlatformBundle\Feature\UpgradeOptions;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class FeatureRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private FeatureGate $gate,
    ) {
    }

    public function isEnabled(string $featureKey, ?SubscribableInterface $for = null): bool
    {
        return $this->gate->isEnabled($featureKey, $for);
    }

    public function canUse(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): bool
    {
        return $this->gate->canUse($featureKey, $currentUsage, $for);
    }

    public function remaining(string $featureKey, int $currentUsage = 0, ?SubscribableInterface $for = null): ?int
    {
        return $this->gate->remaining($featureKey, $currentUsage, $for);
    }

    public function isUnlimited(string $featureKey, ?SubscribableInterface $for = null): bool
    {
        return $this->gate->resolve($featureKey, $for)->isUnlimited();
    }

    public function upgradeOptions(string $featureKey): UpgradeOptions
    {
        return $this->gate->upgradeOptions($featureKey);
    }
}
