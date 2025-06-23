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
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfig;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class PlatformExtension extends Extension implements PrependExtensionInterface
{
    public function __construct(
        private readonly PlatformConfig $platformConfig
    ) {
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->import('services.php');

        $container->setParameter('solidworx_platform.doctrine.types.enable_utc_date', $config['doctrine']['types']['enable_utc_date']);
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('platform', []);

        if ($container->hasExtension('twig')) {
            $path = \dirname(__DIR__) . '/Resources/views';

            $container->prependExtensionConfig(
                'twig',
                [
                    'paths' => [
                        $path => 'SolidWorxPlatform',
                    ],
                ]
            );
        }

        if ($this->platformConfig->get('security.2fa.enabled')) {
            TwoFactorExtension::enable(
                $container,
                [
                    'name' => $this->platformConfig->get('name'),
                    'base_template' => $this->platformConfig->get('security.2fa.base_template'),
                ]
            );
        }
    }
}
