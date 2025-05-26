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
use SolidWorx\Platform\SaasBundle\Exception\ExtensionRequiredException;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezy;
use SolidWorx\Platform\SaasBundle\SolidWorxPlatformSaasBundle;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SolidWorxPlatformSaasExtension extends Extension implements PrependExtensionInterface
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->import('services.php');

        if (isset($config['doctrine']['db_schema']['table_names'])) {
            $loader->import('doctrine.php');

            $container->setParameter('solidworx_platform.saas.doctrine.db_schema.table_names', $config['doctrine']['db_schema']['table_names']);
        }

        if (isset($config['doctrine']['subscriptions']['entity'])) {
            $container->setParameter('solidworx_platform.saas.doctrine.subscribable_class', $config['doctrine']['subscriptions']['entity']);
        }

        if (isset($config['payment']['return_route'])) {
            $container->setParameter('solidworx_platform.saas.payment.return_route', $config['payment']['return_route']);
        }

        foreach ($config['integration']['payment'] ?? [] as $key => $value) {
            if ($value['enabled'] === false) {
                continue;
            }

            if ($key === 'lemon_squeezy') {
                $def = $container->getDefinition(LemonSqueezy::class);
                $def->setBindings([
                    '$apiKey' => $value['api_key'],
                    '$storeId' => $value['store_id'],
                ]);
                $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.api_key', $value['api_key']);
                $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.store_id', $value['store_id']);
                $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.webhook_secret', $value['webhook_secret']);
                $container->setParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.enabled', true);
            }
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if (! $container->hasExtension('doctrine')) {
            throw new ExtensionRequiredException('doctrine');
        }

        if (! $container->hasExtension('framework')) {
            throw new ExtensionRequiredException('doctrine');
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
}
