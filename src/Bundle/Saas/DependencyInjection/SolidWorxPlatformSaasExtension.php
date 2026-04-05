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
use RuntimeException;
use SolidWorx\Platform\SaasBundle\Config\SaasConfiguration;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Exception\ExtensionRequiredException;
use SolidWorx\Platform\SaasBundle\Feature\FeatureConfigRegistry;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezy;
use SolidWorx\Platform\SaasBundle\SolidWorxPlatformSaasBundle;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SolidWorxPlatformSaasExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array{
     *   doctrine: array{
     *     subscriptions: array{entity: string},
     *     trial: array{user_entity: string|null},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string, trial: string}}
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
                'trial' => Trial::class,
            ];

            $tableNames = [];
            foreach ($config['doctrine']['db_schema']['table_names'] as $key => $tableName) {
                $fqcn = $simpleToFqcn[$key] ?? null;
                if ($fqcn === null) {
                    throw new RuntimeException(sprintf(
                        'Unknown table name key "%s" in saas.doctrine.db_schema.table_names configuration. Expected one of: %s.',
                        $key,
                        implode(', ', array_keys($simpleToFqcn)),
                    ));
                }

                $tableNames[$fqcn] = $tableName;
            }

            $container->setParameter('solidworx_platform.saas.doctrine.db_schema.table_names', $tableNames);
        }

        $container->setParameter('solidworx_platform.saas.doctrine.subscribable_class', $config['doctrine']['subscriptions']['entity']);

        if (($config['doctrine']['trial']['user_entity'] ?? null) !== null) {
            $container->setParameter('solidworx_platform.saas.doctrine.trial_user_class', $config['doctrine']['trial']['user_entity']);
        }

        $container->setParameter('solidworx_platform.saas.payment.return_route', $config['payment']['return_route']);

        $lemonSqueezy = $config['integration']['lemon_squeezy'];
        if ($lemonSqueezy['enabled']) {
            if (! $container->hasDefinition(LemonSqueezy::class)) {
                throw new RuntimeException(sprintf(
                    'LemonSqueezy integration is enabled but the service "%s" is not defined. Ensure the bundle services are loaded correctly.',
                    LemonSqueezy::class,
                ));
            }

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

        $resolveTargetEntities = [
            SubscribableInterface::class => SubscribableInterface::class,
        ];

        $rawDoctrine = $this->rawSection['doctrine'] ?? null;
        $rawTrial = is_array($rawDoctrine) ? ($rawDoctrine['trial'] ?? null) : null;
        if (is_array($rawTrial) && ($rawTrial['user_entity'] ?? null) !== null) {
            $resolveTargetEntities[TrialUserInterface::class] = TrialUserInterface::class;
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
                    'resolve_target_entities' => $resolveTargetEntities,
                ],
            ],
        );
    }

    /**
     * @return array{
     *   doctrine: array{
     *     subscriptions: array{entity: string},
     *     trial: array{user_entity: string|null},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string, trial: string}}
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
     *     trial: array{user_entity: string|null},
     *     db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string, trial: string}}
     *   },
     *   payment: array{return_route: string},
     *   integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}},
     *   features: array<string, array{type: string, default: mixed, description: string}>
     * }
     */
    private function processRawSection(): array
    {
        $treeBuilder = (new SaasConfiguration())->getTreeBuilder();

        $processor = new Processor();

        /** @var array{doctrine: array{subscriptions: array{entity: string}, trial: array{user_entity: string|null}, db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string, trial: string}}}, payment: array{return_route: string}, integration: array{lemon_squeezy: array{enabled: bool, api_key: string, webhook_secret: string, store_id: string}}, features: array<string, array{type: string, default: mixed, description: string}>} */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }
}
