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

namespace SolidWorx\Platform\UiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('solidworx_platform_ui');

        //@formatter:off
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('icon_pack')
                        ->info('The icon pack to use')
                        ->isRequired()
                        ->defaultValue('tabler')
                    ->end()
                ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
