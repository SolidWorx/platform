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
use Reflector;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendVerificationEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\CheckResetPasswordEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Security\VerifyEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Tenant\OnboardTenant;
use SolidWorx\Platform\PlatformBundle\Controller\Tenant\SelectTenant;
use SolidWorx\Platform\PlatformBundle\Controller\Tenant\TenantRedirector;
use SolidWorx\Platform\PlatformBundle\DataCollector\TenantDataCollector;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantAwareListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantMetadataListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantWriteGuardListener;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Tenant\TenantOnboardingType;
use SolidWorx\Platform\PlatformBundle\Logger\Processor\TenantLoggingProcessor;
use SolidWorx\Platform\PlatformBundle\Menu\TwoFactorMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Messenger\TenantMiddleware;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use SolidWorx\Platform\PlatformBundle\Repository\ResetPasswordRequestRepository;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\SendVerificationEmailListener;
use SolidWorx\Platform\PlatformBundle\Security\EventListener\TenantDomainLoginListener;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter;
use SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\DefaultTenantOnboarder;
use SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\TenantOnboarder;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\DomainTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\RouteTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\SessionTenantResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeGuardListener;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAccessValidationListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantFilterSynchronizer;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantRequestListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Tenant\Switcher;
use SolidWorx\Platform\PlatformBundle\Validator\Constraint\TwoFactorCodeValidator;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Webmozart\Assert\Assert;
use function interface_exists;

/**
 * @phpstan-type MultiTenancyConfig array{
 *   enabled: bool,
 *   session_key: string,
 *   route_param: string,
 *   validate_user_access: bool,
 *   require_tenant: bool,
 *   default_route: string|null,
 *   onboarding: array{enabled: bool, form_type: class-string},
 *   models: array{tenant: class-string, user_tenant: class-string},
 *   resolvers: array{domain: bool, session: bool, route: bool},
 *   write_guard: array{check_user_access: bool}
 * }
 *
 * @phpstan-type ProfileConfig array{
 *   form_type: class-string,
 *   templates: array{layout: string, show: string, edit: string, change_password: string},
 *   password: array{min_length: int, strength: string, check_compromised: bool}
 * }
 *
 * @phpstan-type EmailVerificationConfig array{
 *   enabled: bool,
 *   lifetime: int,
 *   firewall: string,
 *   banner_template: string,
 *   email: array{template: string, subject: string}
 * }
 *
 * @phpstan-type ResetPasswordConfig array{
 *   enabled: bool,
 *   lifetime: int,
 *   throttle_limit: int,
 *   enable_garbage_collection: bool,
 *   firewall: string,
 *   rate_limit: array{enabled: bool, limit: int, interval: string},
 *   templates: array{request: string, check_email: string, reset: string},
 *   email: array{template: string, subject: string}
 * }
 *
 * @phpstan-type PlatformConfig array{
 *   name: string,
 *   version: string,
 *   security: array{
 *     base_url: string|null,
 *     access_decision: array{strategies: array<string, string>},
 *     two_factor: array{enabled: bool, base_template: string|null},
 *     email_verification: EmailVerificationConfig,
 *     reset_password: ResetPasswordConfig
 *   },
 *   doctrine: array{types: array{enable_utc_date: bool}},
 *   models: array{user: string},
 *   profile: ProfileConfig,
 *   multi_tenancy: MultiTenancyConfig
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
        OnboardTenant::class,
        TenantRedirector::class,
        TenantCreationVoter::class,
        TenantDataCollector::class,
        TenantLoggingProcessor::class,
        TenantLock::class,
        TenantSessionStorage::class,
        TenantScopeResolver::class,
        TenantScopeGuardListener::class,
        TenantDomainLoginListener::class,
        TenantOnboardingType::class,
        DefaultTenantOnboarder::class,
        Switcher::class,
    ];

    /**
     * The rate limiter guarding the reset-password request form, per IP address.
     */
    private const string RESET_PASSWORD_LIMITER = 'solidworx_platform_reset_password';

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
        Assert::allString(array_keys($rawSection));
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getConfig();

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->import('services.php');

        $container->setParameter('solidworx_platform.app.name', $config['name']);
        $container->setParameter('solidworx_platform.doctrine.types.enable_utc_date', $config['doctrine']['types']['enable_utc_date']);

        $container->registerForAutoconfiguration(MenuProviderInterface::class)
            ->addTag('knp_menu.provider');

        $container->registerAttributeForAutoconfiguration(MenuBuilder::class, static function (ChildDefinition $definition, MenuBuilder $attribute, Reflector $reflector): void {
            if (! $reflector instanceof ReflectionMethod) {
                return;
            }

            $definition->addTag(Util::tag('menu.builder'), [
                'alias' => $attribute->name,
                'method' => $reflector->getName(),
                'priority' => $attribute->priority,
                'role' => $attribute->role,
            ]);
        });

        $container->setParameter('solidworx_platform.models.user', $config['models']['user']);
        // Merged over the application's map rather than replaced by it: an application adding an
        // attribute of its own must not silently drop the platform's, whose whole point is that a
        // refusal counts.
        $container->setParameter(
            'solidworx_platform.security.access_decision.strategies',
            PlatformConfiguration::PLATFORM_ACCESS_DECISION_STRATEGIES + $config['security']['access_decision']['strategies'],
        );

        $container->setParameter('solidworx_platform.security.base_url', $config['security']['base_url']);

        $this->loadProfile($container, $config['profile']);

        $container->setParameter('solidworx_platform.security.two_factor.enabled', $config['security']['two_factor']['enabled']);

        if (! $config['security']['two_factor']['enabled']) {
            $container->removeDefinition(ResendTwoFactorCode::class);
            $container->removeDefinition(TwoFactorConfiguration::class);
            // Dropping the builder keeps the "Two-factor authentication" entry — and the link to
            // the route it would generate — out of the user menu entirely.
            $container->removeDefinition(TwoFactorMenuBuilder::class);
            $container->removeDefinition(TwoFactorCodeValidator::class);
            $container->removeDefinition(TwoFactor::class);
        }

        $this->loadEmailVerification($container, $config['security']['email_verification']);

        $this->loadResetPassword($container, $config['security']['reset_password']);

        $this->loadMultiTenancy($container, $config['multi_tenancy']);
    }

    /**
     * With verification disabled the kernel does not register SymfonyCastsVerifyEmailBundle, so
     * everything that signs or checks a link is removed. The gate stays and answers "not gated",
     * so application code injecting it keeps working either way.
     *
     * @param EmailVerificationConfig $config
     */
    private function loadEmailVerification(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('solidworx_platform.security.email_verification.enabled', $config['enabled']);
        $container->setParameter('solidworx_platform.security.email_verification.firewall', $config['firewall']);
        $container->setParameter('solidworx_platform.security.email_verification.banner_template', $config['banner_template']);
        $container->setParameter('solidworx_platform.security.email_verification.email.template', $config['email']['template']);
        $container->setParameter('solidworx_platform.security.email_verification.email.subject', $config['email']['subject']);

        if (! $config['enabled']) {
            $container->removeDefinition(EmailVerifier::class);
            $container->removeDefinition(SendVerificationEmailListener::class);
            $container->removeDefinition(VerifyEmail::class);
            $container->removeDefinition(ResendVerificationEmail::class);
        }
    }

    /**
     * With reset disabled the kernel does not register SymfonyCastsResetPasswordBundle and the
     * request entity is not mapped, so everything built on them goes too.
     *
     * @param ResetPasswordConfig $config
     */
    private function loadResetPassword(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('solidworx_platform.security.reset_password.enabled', $config['enabled']);
        $container->setParameter('solidworx_platform.security.reset_password.firewall', $config['firewall']);
        $container->setParameter('solidworx_platform.security.reset_password.templates.request', $config['templates']['request']);
        $container->setParameter('solidworx_platform.security.reset_password.templates.check_email', $config['templates']['check_email']);
        $container->setParameter('solidworx_platform.security.reset_password.templates.reset', $config['templates']['reset']);
        $container->setParameter('solidworx_platform.security.reset_password.email.template', $config['email']['template']);
        $container->setParameter('solidworx_platform.security.reset_password.email.subject', $config['email']['subject']);

        if (! $config['enabled']) {
            $container->removeDefinition(RequestResetPassword::class);
            $container->removeDefinition(CheckResetPasswordEmail::class);
            $container->removeDefinition(ResetPassword::class);
            $container->removeDefinition(ResetPasswordRequestRepository::class);

            return;
        }

        if ($config['rate_limit']['enabled']) {
            $container->getDefinition(RequestResetPassword::class)
                ->setArgument('$limiter', new Reference('limiter.' . self::RESET_PASSWORD_LIMITER));
        }
    }

    /**
     * @param ProfileConfig $config
     */
    private function loadProfile(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('solidworx_platform.profile.form_type', $config['form_type']);
        $container->setParameter('solidworx_platform.profile.templates.layout', $config['templates']['layout']);
        $container->setParameter('solidworx_platform.profile.templates.show', $config['templates']['show']);
        $container->setParameter('solidworx_platform.profile.templates.edit', $config['templates']['edit']);
        $container->setParameter('solidworx_platform.profile.templates.change_password', $config['templates']['change_password']);
        $container->setParameter('solidworx_platform.profile.password.min_length', $config['password']['min_length']);
        // Stored as the enum rather than its backing value, so PasswordPolicy receives something
        // already validated instead of re-parsing a string it would have to guard against.
        $container->setParameter('solidworx_platform.profile.password.strength', PasswordStrengthLevel::from($config['password']['strength']));
        $container->setParameter('solidworx_platform.profile.password.check_compromised', $config['password']['check_compromised']);
    }

    /**
     * @param MultiTenancyConfig $config
     */
    private function loadMultiTenancy(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('solidworx_platform.multi_tenancy.enabled', $config['enabled']);
        $container->setParameter('solidworx_platform.multi_tenancy.session_key', $config['session_key']);
        $container->setParameter('solidworx_platform.multi_tenancy.route_param', $config['route_param']);
        $container->setParameter('solidworx_platform.multi_tenancy.validate_user_access', $config['validate_user_access']);
        $container->setParameter('solidworx_platform.multi_tenancy.models.tenant', $config['models']['tenant']);
        $container->setParameter('solidworx_platform.multi_tenancy.models.user_tenant', $config['models']['user_tenant']);
        $container->setParameter('solidworx_platform.multi_tenancy.write_guard.check_user_access', $config['write_guard']['check_user_access']);
        $container->setParameter('solidworx_platform.multi_tenancy.require_tenant', $config['require_tenant']);
        $container->setParameter('solidworx_platform.multi_tenancy.default_route', $config['default_route']);
        $container->setParameter('solidworx_platform.multi_tenancy.onboarding.enabled', $config['onboarding']['enabled']);
        $container->setParameter('solidworx_platform.multi_tenancy.onboarding.form_type', $config['onboarding']['form_type']);

        if (! $config['enabled']) {
            foreach (self::MULTI_TENANCY_SERVICES as $serviceId) {
                $container->removeDefinition($serviceId);
            }

            return;
        }

        $container->setAlias(TenantOnboarder::class, DefaultTenantOnboarder::class);

        // The onboarding services stay registered even when onboarding is disabled. Its route is
        // discovered from the controller's #[Route] attribute regardless, so removing the service
        // would turn a request to /tenant/onboarding into a service-resolution failure rather than
        // the 404 the controller already produces.

        if (! $config['require_tenant']) {
            $container->removeDefinition(TenantScopeGuardListener::class);
            $container->removeDefinition(TenantScopeResolver::class);
        }

        if (! $config['resolvers']['domain']) {
            $container->removeDefinition(DomainTenantResolver::class);

            // Without domain resolution there is no custom-domain login to verify.
            $container->removeDefinition(TenantDomainLoginListener::class);
        }

        if (! $config['validate_user_access']) {
            $container->removeDefinition(TenantDomainLoginListener::class);
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
                ->setAutowired(autowired: true)
                ->setAutoconfigured(autoconfigured: true);
        }
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->getConfig();

        if ($container->hasExtension('doctrine')) {
            $orm = [
                'mappings' => [
                    'Platform' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/../Model',
                        'prefix' => 'SolidWorx\Platform\PlatformBundle\Model',
                        'alias' => 'Platform',
                    ],
                    'SolidWorxPlatformBundle' => [
                        'is_bundle' => true,
                        'type' => 'attribute',
                        'dir' => 'Entity',
                        'prefix' => 'SolidWorx\Platform\PlatformBundle\Entity',
                        'alias' => 'PlatformEntity',
                    ],
                ],
                'resolve_target_entities' => [
                    UserInterface::class => $config['models']['user'],
                    TenantInterface::class => $config['multi_tenancy']['models']['tenant'],
                    UserTenantInterface::class => $config['multi_tenancy']['models']['user_tenant'],
                ],
            ];

            if ($config['security']['reset_password']['enabled']) {
                $orm['mappings']['SolidWorxPlatformResetPassword'] = [
                    'is_bundle' => false,
                    'type' => 'attribute',
                    'dir' => __DIR__ . '/../Security/ResetPassword',
                    'prefix' => 'SolidWorx\Platform\PlatformBundle\Security\ResetPassword',
                    'alias' => 'PlatformResetPassword',
                ];
            }

            if ($config['multi_tenancy']['enabled']) {
                $orm['mappings']['SolidWorxPlatformBundle'] = [
                    'is_bundle' => false,
                    'type' => 'attribute',
                    'dir' => __DIR__ . '/../Entity',
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
            $path = __DIR__ . '/../Resources/views';

            $container->prependExtensionConfig(
                'twig',
                [
                    'paths' => [
                        $path => 'Platform',
                    ],
                    'globals' => [
                        // Lets shared layouts reference tenancy-only components without exploding
                        // when multi-tenancy is switched off and those services are gone.
                        'platform_multi_tenancy_enabled' => $config['multi_tenancy']['enabled'],
                        // Lets the login and change-password pages link to the reset form only
                        // when its route exists.
                        'platform_reset_password_enabled' => $config['security']['reset_password']['enabled'],
                    ],
                ]
            );
        }

        if ($this->isAssetMapperAvailable($container)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__ . '/../../../../assets' => '@solidworx/platform',
                    ],
                ],
            ]);
        }

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

        if ($container->hasExtension('symfonycasts_verify_email')) {
            $container->prependExtensionConfig('symfonycasts_verify_email', [
                'lifetime' => $config['security']['email_verification']['lifetime'],
            ]);
        }

        $resetPassword = $config['security']['reset_password'];

        if ($container->hasExtension('symfonycasts_reset_password')) {
            $container->prependExtensionConfig('symfonycasts_reset_password', [
                'request_password_repository' => ResetPasswordRequestRepository::class,
                'lifetime' => $resetPassword['lifetime'],
                'throttle_limit' => $resetPassword['throttle_limit'],
                'enable_garbage_collection' => $resetPassword['enable_garbage_collection'],
            ]);
        }

        if ($resetPassword['enabled'] && $resetPassword['rate_limit']['enabled']) {
            $container->prependExtensionConfig('framework', [
                'rate_limiter' => [
                    self::RESET_PASSWORD_LIMITER => [
                        'policy' => 'sliding_window',
                        'limit' => $resetPassword['rate_limit']['limit'],
                        'interval' => $resetPassword['rate_limit']['interval'],
                    ],
                ],
            ]);
        }

        $container->prependExtensionConfig(
            'security',
            [
                'providers' => [
                    'platform_user' => [
                        'entity' => [
                            'class' => UserInterface::class,
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
        $this->config ??= $this->processRawSection();

        return $this->config;
    }

    /**
     * @return PlatformConfig
     */
    private function processRawSection(): array
    {
        $treeBuilder = new PlatformConfiguration()->getTreeBuilder();

        $processor = new Processor();

        /** @var PlatformConfig */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (! interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        // check that FrameworkBundle 6.3 or higher is installed
        /** @var array{FrameworkBundle?: array{path: string}} $bundlesMetadata */
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (! isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        return is_file($bundlesMetadata['FrameworkBundle']['path'] . '/Resources/config/asset_mapper.php');
    }
}
