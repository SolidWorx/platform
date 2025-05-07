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
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if (! $container->hasExtension('doctrine')) {
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
