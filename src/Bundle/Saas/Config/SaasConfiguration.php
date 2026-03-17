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

namespace SolidWorx\Platform\SaasBundle\Config;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigurationInterface;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use function in_array;
use function is_subclass_of;
use function sprintf;

final class SaasConfiguration implements PlatformConfigurationInterface
{
    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'saas';
    }

    #[Override]
    public function getTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('saas');
        $root = $treeBuilder->getRootNode();

        //@formatter:off
        $root
            ->info('SaaS / subscription configuration')
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
                                    ->info(sprintf('The class name of the subscription entity. Must implement %s', SubscribableInterface::class))
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
                                        ->scalarNode('plan')
                                            ->defaultValue(Plan::TABLE_NAME)
                                            ->info('The table name for the Plan entity')
                                            ->validate()
                                                ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                ->thenInvalid('The table name is not valid')
                                            ->end()
                                        ->end()
                                        ->scalarNode('subscription')
                                            ->defaultValue(Subscription::TABLE_NAME)
                                            ->info('The table name for the Subscription entity')
                                            ->validate()
                                                ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                ->thenInvalid('The table name is not valid')
                                            ->end()
                                        ->end()
                                        ->scalarNode('subscription_log')
                                            ->defaultValue(SubscriptionLog::TABLE_NAME)
                                            ->info('The table name for the Subscription logs entity')
                                            ->validate()
                                                ->ifTrue(fn ($value): bool => in_array(preg_match('/^(?!\d)[A-Za-z_][A-Za-z0-9_$#]{0,64}$/u', (string) $value), [0, false], true))
                                                ->thenInvalid('The table name is not valid')
                                            ->end()
                                        ->end()
                                        ->scalarNode('plan_feature')
                                            ->defaultValue(PlanFeature::TABLE_NAME)
                                            ->info('The table name for the Plan Feature entity')
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
                ->arrayNode('payment')
                    ->isRequired()
                    ->children()
                        ->scalarNode('return_route')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('integration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('lemon_squeezy')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                    ->defaultValue('%env(LEMON_SQUEEZY_API_KEY)%')
                                ->end()
                                ->scalarNode('webhook_secret')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                    ->defaultValue('%env(LEMON_SQUEEZY_WEBHOOK_SECRET)%')
                                ->end()
                                ->scalarNode('store_id')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                    ->defaultValue('%env(LEMON_SQUEEZY_STORE_ID)%')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('features')
                    ->info('Define available plan features and their defaults')
                    ->useAttributeAsKey('name')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')
                                ->values(['boolean', 'integer', 'string', 'array'])
                                ->isRequired()
                                ->info('The data type of the feature value')
                            ->end()
                            ->variableNode('default')
                                ->isRequired()
                                ->info('The default value for this feature')
                            ->end()
                            ->scalarNode('description')
                                ->defaultValue('')
                                ->info('A human-readable description of this feature')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
