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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass;

use Closure;
use Override;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Util;
use SolidWorx\Platform\PlatformBundle\Menu\Provider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class MenuCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(Provider::class)) {
            return;
        }

        $definition = $container->getDefinition(Provider::class);

        $taggedServices = $container->findTaggedServiceIds(Util::tag('menu.builder'));

        foreach ($taggedServices as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {

                $wrapperDefinition = (new Definition(Closure::class))
                    ->addArgument([new Reference($id), $attributes['method']])
                    ->setFactory(Closure::fromCallable(...));

                $definition->addMethodCall(
                    'addBuilder',
                    [
                        $wrapperDefinition,
                        $attributes['alias'],
                        $attributes['priority'],
                        $attributes['role'],
                    ],
                );
            }
        }
    }
}
