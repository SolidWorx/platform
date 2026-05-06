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

namespace SolidWorx\Platform\PlatformBundle\Twig\Extension;

use Override;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\FeatureRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FeatureExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature_enabled', [FeatureRuntime::class, 'isEnabled']),
            new TwigFunction('feature_can_use', [FeatureRuntime::class, 'canUse']),
            new TwigFunction('feature_remaining', [FeatureRuntime::class, 'remaining']),
            new TwigFunction('feature_unlimited', [FeatureRuntime::class, 'isUnlimited']),
            new TwigFunction('feature_upgrade', [FeatureRuntime::class, 'upgradeOptions']),
        ];
    }
}
