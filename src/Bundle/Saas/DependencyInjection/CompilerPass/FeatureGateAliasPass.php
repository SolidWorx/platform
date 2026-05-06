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

namespace SolidWorx\Platform\SaasBundle\DependencyInjection\CompilerPass;

use Override;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureGate;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Overrides the FeatureGate alias to point at PlanFeatureGate.
 *
 * This runs as a compiler pass (after all extension load() calls) so it
 * takes precedence over the NoopFeatureGate alias defined in PlatformBundle's
 * services.php, regardless of bundle registration order.
 */
final class FeatureGateAliasPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(FeatureGate::class, PlanFeatureGate::class)
            ->setPublic(false);
    }
}
