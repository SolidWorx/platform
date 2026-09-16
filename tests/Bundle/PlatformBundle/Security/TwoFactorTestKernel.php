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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security;

use Override;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceTokenStorage;
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\BackupCodeGeneratorInterface;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\PlatformBundle\Twig\Extension\ProfileExtension;
use SolidWorx\Platform\PlatformBundle\Validator\Constraint\TwoFactorCodeValidator;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubBackupCodeGenerator;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubTotpAuthenticator;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubTrustedDeviceManager;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubUserProvider;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubUserRepository;
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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Boots just enough of Symfony to render the two-factor settings template.
 *
 * The live component itself is not mounted — it needs Doctrine, the scheb services and a session —
 * so the template is rendered directly with the variables the component would expose. That is on
 * purpose: the behaviour lives in PHP and is unit-tested there, while the template is the part
 * that fails at runtime with nothing to catch it. `live_action()` builds Stimulus attributes from
 * its arguments alone, so it works outside a mounted component.
 *
 * LiveComponentBundle is registered only so `live_action()` exists; the Ui components, the icons
 * and the Stimulus helpers are all the real ones.
 */
final class TwoFactorTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * A public alias for the form factory, which the container would otherwise inline.
     */
    public const string FORM_FACTORY = 'test.two_factor.form_factory';

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
        yield new LiveComponentBundle();
        yield new StimulusBundle();
        yield new UXIconsBundle();
        yield new SchebTwoFactorBundle();
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_two_factor_test/cache/' . $this->environment;
    }

    #[Override]
    public function getBuildDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_two_factor_test/build/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_two_factor_test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = dirname(__DIR__, 4);
        $fixtures = dirname(__DIR__) . '/Profile/fixtures';

        $container->extension('framework', [
            'secret' => 'two-factor-test',
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
            'providers' => [
                'stub' => [
                    'id' => StubUserProvider::class,
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'stub',
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

        // Registering the bundle's own configuration is what defines
        // `scheb_two_factor.trusted_token_storage` and its JWT encoder — both marked internal, so
        // they are not ours to build by hand.
        $container->extension('scheb_two_factor', [
            'security_tokens' => [UsernamePasswordToken::class],
            'trusted_device' => [
                'enabled' => true,
            ],
        ]);

        $container->extension('ux_icons', [
            'icon_dir' => $fixtures,
            'ignore_not_found' => true,
            'iconify' => [
                'on_demand' => false,
            ],
        ]);

        $container->parameters()
            ->set('solidworx_platform.app.name', 'Acme Platform');

        $services = $container->services()
            ->defaults()
            ->autoconfigure()
            ->autowire();

        $services->alias(self::FORM_FACTORY, 'form.factory')
            ->public();

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

        $services->set(ProfileExtension::class)
            ->args(['@SolidWorxPlatform/Profile/layout.html.twig'])
            ->tag('twig.extension');

        // The `TwoFactorCode` constraint resolves its validator from the container as soon as the
        // verification form is validated, so both have to exist even though no code is checked here.
        $services->set(TotpAuthenticatorInterface::class, StubTotpAuthenticator::class);

        $services->set(TwoFactorCodeValidator::class)
            ->args([service(TotpAuthenticatorInterface::class), service('security.helper')])
            ->tag('validator.constraint_validator');

        // Enough of the component's collaborators to mount it for real. None of them persist or
        // encrypt anything — what is under test is the markup and the props it hands the browser.
        $services->set(StubUserProvider::class)
            ->public();

        $services->set(UserRepository::class, StubUserRepository::class);
        $services->set(TrustedDeviceManagerInterface::class, StubTrustedDeviceManager::class);
        $services->set(BackupCodeGeneratorInterface::class, StubBackupCodeGenerator::class);
        $services->set(SluggerInterface::class, AsciiSlugger::class);

        $services->alias('scheb_two_factor.default_trusted_device_manager', TrustedDeviceManagerInterface::class);
        $services->alias(TrustedDeviceTokenStorage::class, 'scheb_two_factor.trusted_token_storage');

        $services->set(TwoFactor::class)
            ->tag('twig.component', [
                'key' => 'Platform:Security:TwoFactor',
                'template' => '@SolidWorxPlatform/Components/Security/two_factor.html.twig',
                'live' => true,
            ])
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The live component renders a URL to its own endpoint, so the route has to exist even
        // though nothing here posts to it.
        $routes->import('@LiveComponentBundle/config/routes.php')
            ->prefix('/_components');
    }
}
