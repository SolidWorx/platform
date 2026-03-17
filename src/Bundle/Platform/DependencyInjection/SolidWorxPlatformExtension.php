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
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\PlatformBundle\Validator\Constraint\TwoFactorCodeValidator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function dirname;

final class SolidWorxPlatformExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array{
     *   name: string,
     *   version: string,
     *   security: array{two_factor: array{enabled: bool, base_template: string|null}},
     *   doctrine: array{types: array{enable_utc_date: bool}},
     *   models: array{user: string}
     * }|null
     */
    private ?array $config = null;

    /**
     * @param array<string, mixed> $rawSection The raw (unvalidated) `platform:` config section.
     */
    public function __construct(
        private readonly array $rawSection
    ) {
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getConfig();

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->import('services.php');

        $container->setParameter('solidworx_platform.app.name', $config['name']);
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

        if (! $config['security']['two_factor']['enabled']) {
            // @TODO: Need to remove the 2FA routes as well if 2fa is not configured
            $container->removeDefinition(ResendTwoFactorCode::class);
            $container->removeDefinition(TwoFactorCodeValidator::class);
            $container->removeDefinition(TwoFactor::class);
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig(
                'doctrine',
                [
                    'dbal' => [
                        'types' => [
                            URLType::NAME => URLType::class,
                        ],
                    ],
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

        $config = $this->getConfig();

        if ($config['security']['two_factor']['enabled']) {
            TwoFactorExtension::enable(
                $container,
                [
                    'name' => $config['name'],
                    'base_template' => $config['security']['two_factor']['base_template'] ?? '',
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

    /**
     * @return array{
     *   name: string,
     *   version: string,
     *   security: array{two_factor: array{enabled: bool, base_template: string|null}},
     *   doctrine: array{types: array{enable_utc_date: bool}},
     *   models: array{user: string}
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
     *   name: string,
     *   version: string,
     *   security: array{two_factor: array{enabled: bool, base_template: string|null}},
     *   doctrine: array{types: array{enable_utc_date: bool}},
     *   models: array{user: string}
     * }
     */
    private function processRawSection(): array
    {
        $treeBuilder = (new PlatformConfiguration())->getTreeBuilder();

        $processor = new Processor();

        /** @var array{name: string, version: string, security: array{two_factor: array{enabled: bool, base_template: string|null}}, doctrine: array{types: array{enable_utc_date: bool}}, models: array{user: string}} */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }
}
