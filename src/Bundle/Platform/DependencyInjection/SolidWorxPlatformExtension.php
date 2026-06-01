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
use SolidWorx\Platform\PlatformBundle\Controller\Tenant\SelectTenant;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantAwareListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantMetadataListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantWriteGuardListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;
use SolidWorx\Platform\PlatformBundle\Messenger\TenantMiddleware;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\DomainTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\RouteTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\SessionTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAccessValidationListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantFilterSynchronizer;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantRequestListener;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\PlatformBundle\Validator\Constraint\TwoFactorCodeValidator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use function dirname;
use function interface_exists;

/**
 * @phpstan-type PlatformConfig array{
 *   name: string,
 *   version: string,
 *   security: array{two_factor: array{enabled: bool, base_template: string|null}},
 *   doctrine: array{types: array{enable_utc_date: bool}},
 *   models: array{user: string},
 *   multi_tenancy: array{
 *     enabled: bool,
 *     session_key: string,
 *     route_param: string,
 *     validate_user_access: bool,
 *     resolvers: array{domain: bool, session: bool, route: bool},
 *     write_guard: array{check_user_access: bool}
 *   }
 * }
 */
final class SolidWorxPlatformExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Tenancy service ids removed when multi-tenancy is disabled.
     *
     * @var list<class-string>
     */
    private const array MULTI_TENANCY_SERVICES = [
        TenantContext::class,
        TenantManager::class,
        TenantFilterSynchronizer::class,
        TenantAccessValidationListener::class,
        TenantRequestListener::class,
        TenantMetadataListener::class,
        TenantAwareListener::class,
        TenantWriteGuardListener::class,
        DomainTenantResolver::class,
        SessionTenantResolver::class,
        RouteTenantResolver::class,
        TenantVoter::class,
        TenantRepository::class,
        UserTenantRepository::class,
        SelectTenant::class,
    ];

    /**
     * @var PlatformConfig|null
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

        $this->loadMultiTenancy($container, $config['multi_tenancy']);
    }

    /**
     * @param array{
     *   enabled: bool,
     *   session_key: string,
     *   route_param: string,
     *   validate_user_access: bool,
     *   resolvers: array{domain: bool, session: bool, route: bool},
     *   write_guard: array{check_user_access: bool}
     * } $config
     */
    private function loadMultiTenancy(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('solidworx_platform.multi_tenancy.enabled', $config['enabled']);
        $container->setParameter('solidworx_platform.multi_tenancy.session_key', $config['session_key']);
        $container->setParameter('solidworx_platform.multi_tenancy.route_param', $config['route_param']);
        $container->setParameter('solidworx_platform.multi_tenancy.validate_user_access', $config['validate_user_access']);
        $container->setParameter('solidworx_platform.multi_tenancy.write_guard.check_user_access', $config['write_guard']['check_user_access']);

        if (! $config['enabled']) {
            foreach (self::MULTI_TENANCY_SERVICES as $serviceId) {
                $container->removeDefinition($serviceId);
            }

            return;
        }

        if (! $config['resolvers']['domain']) {
            $container->removeDefinition(DomainTenantResolver::class);
        }

        if (! $config['resolvers']['session']) {
            $container->removeDefinition(SessionTenantResolver::class);
        }

        if (! $config['resolvers']['route']) {
            $container->removeDefinition(RouteTenantResolver::class);
        }

        // The Messenger integration is optional: only register the middleware when the component is
        // installed. Add it to your bus middleware to propagate the tenant across the bus.
        if (interface_exists(MiddlewareInterface::class)) {
            $container->register(TenantMiddleware::class, TenantMiddleware::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine')) {
            $orm = [
                'mappings' => [
                    'Platform' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => dirname(__DIR__) . '/Model',
                        'prefix' => 'SolidWorx\Platform\PlatformBundle\Model',
                        'alias' => 'Platform',
                    ],
                ],
            ];

            if ($this->getConfig()['multi_tenancy']['enabled']) {
                $orm['mappings']['PlatformEntity'] = [
                    'is_bundle' => false,
                    'type' => 'attribute',
                    'dir' => dirname(__DIR__) . '/Entity',
                    'prefix' => 'SolidWorx\Platform\PlatformBundle\Entity',
                    'alias' => 'PlatformEntity',
                ];

                $orm['filters'] = [
                    TenantFilter::NAME => [
                        'class' => TenantFilter::class,
                        'enabled' => false,
                    ],
                ];
            }

            $container->prependExtensionConfig(
                'doctrine',
                [
                    'dbal' => [
                        'types' => [
                            URLType::NAME => URLType::class,
                        ],
                    ],
                    'orm' => $orm,
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
     * @return PlatformConfig
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = $this->processRawSection();
        }

        return $this->config;
    }

    /**
     * @return PlatformConfig
     */
    private function processRawSection(): array
    {
        $treeBuilder = (new PlatformConfiguration())->getTreeBuilder();

        $processor = new Processor();

        /** @var PlatformConfig */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }
}
