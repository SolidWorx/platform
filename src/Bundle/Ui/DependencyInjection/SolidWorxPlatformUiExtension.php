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

use Override;
use SolidWorx\Platform\UiBundle\Twig\UiExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function dirname;

final class SolidWorxPlatformUiExtension extends Extension implements PrependExtensionInterface
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->import('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container
            ->getDefinition(UiExtension::class)
            ->setArgument(0, $config['templates']['base'])
        ;

        $container->setParameter('solidworx_platform_ui.template.login', $config['templates']['login']);
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig_component')) {
            $container->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'UiBundle\Twig\Component\\' => [
                        'template_directory' => '@Ui/components/Ui/',
                        'name_prefix' => 'Ui',
                    ],
                ],
            ]);
        }

        // Check if AssetMapper is installed
        /*if ($container->hasExtension('framework') && class_exists(AssetMapper::class)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__ . '/../../../assets/ui/dist' => '@solidworx/ui-component',
                    ],
                ],
            ]);
        }*/

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    dirname(__DIR__) . '/templates/' => 'Ui',
                ],
            ]);
        }
    }
}
