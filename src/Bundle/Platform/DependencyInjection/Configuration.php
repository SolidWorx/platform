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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection;

use Override;
use SolidWorx\Platform\PlatformBundle\Model\User;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    #[Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('solid_worx_platform');

        //@formatter:off
        $treeBuilder
            ->getRootNode()
            ->children()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('types')
                                ->fixXmlConfig('type')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('enable_utc_date')
                                    ->defaultTrue()
                                    ->info('Enable UTC date type. This ensures that all dates are stored in UTC format in the database.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('models')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('user')
                            ->defaultValue(User::class)
                            ->info('The User model class.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
