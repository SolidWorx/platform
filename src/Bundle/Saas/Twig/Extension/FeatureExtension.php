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

namespace SolidWorx\Platform\SaasBundle\Twig\Extension;

use Override;
use SolidWorx\Platform\SaasBundle\Twig\Runtime\FeatureRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for plan feature functions.
 *
 * Provides the following functions:
 * - has_feature(subscriber, feature_key): Check if subscriber has a feature enabled
 * - feature_value(subscriber, feature_key): Get the feature value for a subscriber
 * - can_use_feature(subscriber, feature_key, usage): Check if usage is within limit
 * - feature_remaining(subscriber, feature_key, usage): Get remaining quota
 */
final class FeatureExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_feature', [FeatureRuntime::class, 'hasFeature']),
            new TwigFunction('feature_value', [FeatureRuntime::class, 'getFeatureValue']),
            new TwigFunction('can_use_feature', [FeatureRuntime::class, 'canUseFeature']),
            new TwigFunction('feature_remaining', [FeatureRuntime::class, 'getRemainingQuota']),
            new TwigFunction('is_feature_unlimited', [FeatureRuntime::class, 'isUnlimited']),
        ];
    }
}
