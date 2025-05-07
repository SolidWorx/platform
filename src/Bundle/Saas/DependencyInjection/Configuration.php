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

namespace SolidWorx\Platform\SaasBundle\DependencyInjection;

use Override;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    #[Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('saas');

        //@formatter:off
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->arrayNode('doctrine')
                        ->children()
                            ->arrayNode('db_schema')
                                ->children()
                                    ->arrayNode('table_names')
                                        ->children()
                                            ->scalarNode(Plan::class)
                                                ->defaultValue('saas_plan')
                                                ->info('The table name for the Plan entity')
                                                ->validate()
                                                    ->ifTrue(fn ($value) => ! preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', $value))
                                                        ->thenInvalid('The table name is not valid')
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
