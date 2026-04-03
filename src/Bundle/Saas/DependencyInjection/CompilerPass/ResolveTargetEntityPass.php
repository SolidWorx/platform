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
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ResolveTargetEntityPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $def = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');

        if ($container->hasParameter('solidworx_platform.saas.doctrine.subscribable_class')) {
            $def->addMethodCall(
                'addResolveTargetEntity',
                [SubscribableInterface::class, $container->getParameter('solidworx_platform.saas.doctrine.subscribable_class'), []]
            );
        }

        if ($container->hasParameter('solidworx_platform.saas.doctrine.trial_user_class')) {
            $def->addMethodCall(
                'addResolveTargetEntity',
                [TrialUserInterface::class, $container->getParameter('solidworx_platform.saas.doctrine.trial_user_class'), []]
            );
        }
    }
}
