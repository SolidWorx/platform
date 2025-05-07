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
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    #[Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('solid_worx_platform_saas');

        //@formatter:off
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->arrayNode('doctrine')
                        ->isRequired()
                        ->children()
                            ->arrayNode('subscriptions')
                                ->isRequired()
                                ->children()
                                    ->scalarNode('entity')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->info(sprintf('The class name of the subscription entity. This should be a fully qualified class name. This entity should implement %s', SubscribableInterface::class))
                                        ->validate()
                                            ->ifTrue(fn ($v): bool => ! is_subclass_of($v, SubscribableInterface::class))
                                            ->thenInvalid(sprintf('The subscription entity must implement %s', SubscribableInterface::class))
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('db_schema')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('table_names')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->scalarNode(Plan::class)
                                                ->defaultValue(Plan::TABLE_NAME)
                                                ->info('The table name for the Plan entity')
                                                ->validate()
                                                    ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                    ->thenInvalid('The table name is not valid')
                                                ->end()
                                            ->end()
                                            ->scalarNode(Subscription::class)
                                                ->defaultValue(Subscription::TABLE_NAME)
                                                ->info('The table name for the Subscription entity')
                                                ->validate()
                                                    ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                    ->thenInvalid('The table name is not valid')
                                                ->end()
                                            ->end()
                                            ->scalarNode(SubscriptionLog::class)
                                                ->defaultValue(SubscriptionLog::TABLE_NAME)
                                                ->info('The table name for the Subscription logs entity')
                                                ->validate()
                                                    ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                    ->thenInvalid('The table name is not valid')
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
