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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Profile;

use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Override;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ChangePassword;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\EditProfile;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ChangePasswordType;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicy;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use SolidWorx\Platform\PlatformBundle\Twig\Extension\MenuExtension;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\MenuRuntime;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use SolidWorx\Platform\UiBundle\Layout\LayoutResolver;
use SolidWorx\Platform\UiBundle\Twig\Runtime\LayoutRuntime;
use SolidWorx\Platform\UiBundle\Twig\UiExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Boots just enough of Symfony to render the profile pages.
 *
 * Same trade-off as {@see \SolidWorx\Platform\Tests\Bundle\Ui\Layout\LayoutTestKernel}: the
 * platform bundles are not registered — they would drag in Doctrine and a database — so the few
 * services the templates need are wired by hand. The templates, the form types, the layouts and
 * the Twig runtime are all the real ones, which is the point: a broken block, a renamed macro or
 * a field that stops rendering shows up here.
 *
 * The three profile routes and the two-factor route exist so the templates can generate the URLs
 * they link to; they deliberately have no controllers, because nothing here dispatches a request.
 */
final class ProfileTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * A public alias for the form factory, which the container would otherwise inline.
     */
    public const string FORM_FACTORY = 'test.profile.form_factory';

    /**
     * @return iterable<Bundle>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new TwigComponentBundle();
        yield new StimulusBundle();
        yield new UXIconsBundle();
        yield new WebpackEncoreBundle();
        yield new KnpMenuBundle();
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_profile_test/cache/' . $this->environment;
    }

    #[Override]
    public function getBuildDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_profile_test/build/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_profile_test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = dirname(__DIR__, 4);
        $fixtures = __DIR__ . '/fixtures';

        $container->extension('framework', [
            'secret' => 'profile-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'router' => [
                'utf8' => true,
            ],
            'assets' => [],
            'csrf_protection' => true,
            'form' => true,
            'validation' => [
                'enabled' => true,
                'email_validation_mode' => 'html5',
            ],
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
                'handler_id' => null,
                'cookie_secure' => 'auto',
                'cookie_samesite' => 'lax',
            ],
        ]);

        $container->extension('security', [
            'password_hashers' => [
                InMemoryUser::class => [
                    'algorithm' => 'plaintext',
                ],
            ],
            'providers' => [
                'in_memory' => [
                    'memory' => [
                        'users' => [],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'in_memory',
                    // The user dropdown in the layout renders a logout form, which needs a
                    // logout listener registered for the firewall the token names.
                    'logout' => [
                        'path' => '/logout',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', [
            'paths' => [
                $projectDir . '/src/Bundle/Ui/templates' => 'Ui',
                $projectDir . '/src/Bundle/Platform/Resources/views' => 'SolidWorxPlatform',
            ],
        ]);

        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components',
        ]);

        $container->extension('ux_icons', [
            'icon_dir' => $fixtures,
            'ignore_not_found' => true,
            'iconify' => [
                'on_demand' => false,
            ],
        ]);

        $container->extension('webpack_encore', [
            'output_path' => $fixtures . '/build',
            'strict_mode' => false,
        ]);

        $container->extension('knp_menu', [
            'default_renderer' => 'twig',
            'twig' => [
                'template' => '@SolidWorxPlatform/Menu/menu.html.twig',
            ],
        ]);

        $services = $container->services()
            ->defaults()
            ->autoconfigure()
            ->autowire();

        // Both are private, and the profile tests build forms and read the policy directly.
        $services->alias(self::FORM_FACTORY, 'form.factory')
            ->public();

        $services->alias(PasswordPolicyInterface::class, PasswordPolicy::class)
            ->public();

        $services->set(MenuRuntime::class)
            ->arg('$menuProvider', service('knp_menu.menu_provider'))
            ->tag('twig.runtime');

        $services->set(MenuExtension::class)
            ->tag('twig.extension');

        $services->set(LayoutResolver::class)
            ->args([[]]);

        $services->set(LayoutRuntime::class)
            ->args([service(LayoutResolver::class)])
            ->tag('twig.runtime');

        $services->set(UiExtension::class)
            ->args([
                '@Ui/Layout/base.html.twig',
                [
                    'app' => '@Ui/Layout/app.html.twig',
                    'condensed' => '@Ui/Layout/condensed.html.twig',
                    'clean' => '@Ui/Layout/clean.html.twig',
                ],
                'Acme Platform',
            ])
            ->tag('twig.extension');

        // The two form types under test, wired the way the platform wires them: the profile form
        // against the configured user class, and the password form against the configured policy.
        $services->set(ProfileType::class)
            ->args([ProfileUser::class])
            ->tag('form.type');

        $services->set(PasswordPolicy::class)
            ->args([12, PasswordStrengthLevel::Medium, true]);

        $services->set(ChangePasswordType::class)
            ->args([service(PasswordPolicy::class)])
            ->tag('form.type');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add(ShowProfile::ROUTE_NAME, ShowProfile::PATH);
        $routes->add(EditProfile::ROUTE_NAME, EditProfile::PATH);
        $routes->add(ChangePassword::ROUTE_NAME, ChangePassword::PATH);
        $routes->add(TwoFactorConfiguration::ROUTE_NAME, TwoFactorConfiguration::PATH);
    }
}
