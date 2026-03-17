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
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Exception\ExtensionRequiredException;
use SolidWorx\Platform\SaasBundle\Feature\FeatureConfigRegistry;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezy;
use SolidWorx\Platform\SaasBundle\SolidWorxPlatformSaasBundle;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function in_array;

final class SolidWorxPlatformSaasExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array{
     *   doctrine: array{
     *     subscriptions: array{entity: string},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}
     *   },
     *   payment: array{return_route: string},
     *   integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}},
     *   features: array<string, array{type: string, default: mixed, description: string}>
     * }|null
     */
    private ?array $config = null;

    /**
     * @param array<string, mixed> $rawSection The raw (unvalidated) `platform.saas:` config section.
     */
    public function __construct(
        private readonly array $rawSection
    ) {
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getConfig();

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->import('services.php');

        if (isset($config['doctrine']['db_schema']['table_names'])) {
            $loader->import('doctrine.php');

            // Convert simple name keys (plan, subscription, …) to FQCN keys required by MetadataSubscriber
            $simpleToFqcn = [
                'plan' => Plan::class,
                'subscription' => Subscription::class,
                'subscription_log' => SubscriptionLog::class,
                'plan_feature' => PlanFeature::class,
            ];

            $tableNames = [];
            foreach ($config['doctrine']['db_schema']['table_names'] as $key => $tableName) {
                $fqcn = $simpleToFqcn[$key] ?? $key;
                $tableNames[$fqcn] = $tableName;
            }

            $container->setParameter('solidworx_platform.saas.doctrine.db_schema.table_names', $tableNames);
        }

        $container->setParameter('solidworx_platform.saas.doctrine.subscribable_class', $config['doctrine']['subscriptions']['entity']);
        $container->setParameter('solidworx_platform.saas.payment.return_route', $config['payment']['return_route']);

        $lemonSqueezy = $config['integration']['lemon_squeezy'];
        if ($lemonSqueezy['enabled']) {
            $def = $container->getDefinition(LemonSqueezy::class);
            $def->setBindings([
                '$apiKey' => $lemonSqueezy['api_key'],
                '$storeId' => $lemonSqueezy['store_id'],
            ]);
            $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.api_key', $lemonSqueezy['api_key']);
            $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.store_id', $lemonSqueezy['store_id']);
            $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.webhook_secret', $lemonSqueezy['webhook_secret']);
            $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.enabled', true);
        }

        $features = $config['features'];
        $container->setParameter('solidworx_platform.saas.features', $features);

        if ($container->hasDefinition(FeatureConfigRegistry::class)) {
            $def = $container->getDefinition(FeatureConfigRegistry::class);
            $def->setArgument('$featureConfigs', $features);
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if (! $container->hasExtension('doctrine')) {
            throw new ExtensionRequiredException('doctrine');
        }

        if (! $container->hasExtension('framework')) {
            throw new ExtensionRequiredException('framework');
        }

        $container->prependExtensionConfig(
            'doctrine',
            [
                'orm' => [
                    'mappings' => [
                        'saas' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../Entity',
                            'prefix' => SolidWorxPlatformSaasBundle::NAMESPACE . '\Entity',
                            'alias' => 'Saas',
                        ],
                    ],
                    'resolve_target_entities' => [
                        SubscribableInterface::class => SubscribableInterface::class,
                    ],
                ],
            ],
        );
    }

    /**
     * @return array{
     *   doctrine: array{
     *     subscriptions: array{entity: string},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}
     *   },
     *   payment: array{return_route: string},
     *   integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}},
     *   features: array<string, array{type: string, default: mixed, description: string}>
     * }
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = $this->processRawSection();
        }

        return $this->config;
    }

    /**
     * @return array{
     *   doctrine: array{
     *     subscriptions: array{entity: string},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}
     *   },
     *   payment: array{return_route: string},
     *   integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}},
     *   features: array<string, array{type: string, default: mixed, description: string}>
     * }
     */
    private function processRawSection(): array
    {
        $treeBuilder = new TreeBuilder('saas');
        $root = $treeBuilder->getRootNode();

        //@formatter:off
        $root
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

        $processor = new Processor();

        /** @var array{doctrine: array{subscriptions: array{entity: string}, db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}}, payment: array{return_route: string}, integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}}, features: array<string, array{type: string, default: mixed, description: string}>} */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }
}
