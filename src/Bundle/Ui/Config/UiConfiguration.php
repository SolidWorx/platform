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

namespace SolidWorx\Platform\UiBundle\Config;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class UiConfiguration implements PlatformConfigurationInterface
{
    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'ui';
    }

    #[Override]
    public function getTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ui');
        $root = $treeBuilder->getRootNode();

        //@formatter:off
        $root
            ->info('UI / presentation configuration')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('icon_pack')
                    ->info('The icon pack to use')
                    ->defaultValue('tabler')
                ->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base')
                            ->info('The base template')
                            ->defaultValue('@Ui/Layout/base.html.twig')
                        ->end()
                        ->scalarNode('login')
                            ->info('The standard login template')
                            ->defaultValue('@Ui/Security/login.html.twig')
                        ->end()
                    ->end()
                ->end()
            ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
