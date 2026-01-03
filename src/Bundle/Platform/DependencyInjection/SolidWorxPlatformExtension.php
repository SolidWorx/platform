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

use Knp\Menu\Provider\MenuProviderInterface;
use Override;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfig;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\PlatformBundle\Validator\Constraint\TwoFactorCodeValidator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function dirname;

final class SolidWorxPlatformExtension extends Extension implements PrependExtensionInterface
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

        $container->setParameter('solidworx_platform.app.name', $this->platformConfig?->get('name'));

        $container->setParameter('solidworx_platform.doctrine.types.enable_utc_date', $config['doctrine']['types']['enable_utc_date']);

        $container->registerForAutoconfiguration(MenuProviderInterface::class)
            ->addTag('knp_menu.provider');

        $container->registerAttributeForAutoconfiguration(MenuBuilder::class, static function (ChildDefinition $definition, MenuBuilder $attribute, ReflectionMethod $reflectionMethod): void {
            $definition->addTag(Util::tag('menu.builder'), [
                'alias' => $attribute->name,
                'method' => $reflectionMethod->getName(),
                'priority' => $attribute->priority,
                'role' => $attribute->role,
            ]);
        });

        $container->setParameter('solidworx_platform.models.user', $config['models']['user']);

        if (! $this->platformConfig?->get('security.2fa.enabled')) {
            // @TODO: Need to remove the 2FA routes as well if 2fa is not configured
            $container->removeDefinition(ResendTwoFactorCode::class);
            $container->removeDefinition(TwoFactorCodeValidator::class);
            $container->removeDefinition(TwoFactor::class);
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('platform', []);

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig(
                'doctrine',
                [
                    'orm' => [
                        'mappings' => [
                            'Platform' => [
                                'is_bundle' => false,
                                'type' => 'attribute',
                                'dir' => dirname(__DIR__) . '/Model',
                                'prefix' => 'SolidWorx\Platform\PlatformBundle\Model',
                                'alias' => 'Platform',
                            ],
                        ],
                    ],
                ]
            );
        }

        if ($container->hasExtension('twig')) {
            $path = dirname(__DIR__) . '/Resources/views';

            $container->prependExtensionConfig(
                'twig',
                [
                    'paths' => [
                        $path => 'Platform',
                    ],
                ]
            );
        }

        if ($this->platformConfig?->get('security.2fa.enabled') === true) {
            TwoFactorExtension::enable(
                $container,
                [
                    'name' => $this->platformConfig?->get('name'),
                    'base_template' => $this->platformConfig?->get('security.2fa.base_template'),
                ]
            );
        }

        if ($container->hasExtension('knp_menu')) {
            $container->prependExtensionConfig(
                'knp_menu',
                [
                    'twig' => [
                        'template' => '@SolidWorxPlatform/Menu/menu.html.twig',
                    ],
                    'default_renderer' => 'twig',
                    'providers' => [
                        'builder_alias' => false,
                    ],
                ]
            );
        }

        if ($container->hasExtension('symfonycasts_reset_password')) {
            $container->prependExtensionConfig(
                'symfonycasts_reset_password',
                [],
            );
        }

        /*$container->prependExtensionConfig(
            'framework',
            [
                'csrf_protection' => [
                    'enabled' => true,
                    'stateless_token_ids' => ['submit', 'authenticate', 'logout'],
                ],
            ],
        );*/

        $container->prependExtensionConfig(
            'security',
            [
                'providers' => [
                    'platform_user' => [
                        'entity' => [
                            'class' => User::class,
                        ],
                    ],
                ],
            ],
        );
    }
}
