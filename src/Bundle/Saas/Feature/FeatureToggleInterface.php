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

use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;

/**
 * Interface for feature toggle implementations.
 *
 * This provides a simple abstraction for feature checking that can be used
 * as a bridge to external feature toggle libraries like SolidWorx/Toggler.
 */
interface FeatureToggleInterface
{
    /**
     * Check if a feature is active for a subscriber.
     */
    public function isActive(string $featureKey, SubscribableInterface $subscriber): bool;

    /**
     * Get the value of a feature for a subscriber.
     *
     * @return int|bool|string|array<mixed>
     */
    public function getValue(string $featureKey, SubscribableInterface $subscriber): int|bool|string|array;

    /**
     * Check if a feature is defined.
     */
    public function hasFeature(string $featureKey): bool;
}
