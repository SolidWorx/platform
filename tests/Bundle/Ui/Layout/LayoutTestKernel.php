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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Layout;

use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Override;
use SolidWorx\Platform\PlatformBundle\Twig\Extension\MenuExtension;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\MenuRuntime;
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
 * Boots just enough of Symfony to render the platform's Twig layouts.
 *
 * The platform bundles themselves are not registered — they drag in Doctrine, Messenger and a
 * database — so the handful of Twig services the layouts depend on (the menu functions and the UI
 * globals) are wired by hand instead. Everything else is real: Tabler markup is produced by the
 * actual templates, through the actual Twig runtime.
 */
final class LayoutTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string USERNAME = 'user@example.com';

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
        return sys_get_temp_dir() . '/solidworx_layout_test/cache/' . $this->environment;
    }

    #[Override]
    public function getBuildDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_layout_test/build/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_layout_test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = dirname(__DIR__, 4);
        $fixtures = __DIR__ . '/fixtures';

        $container->extension('framework', [
            'secret' => 'layout-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'router' => [
                'utf8' => true,
            ],
            'assets' => [],
            'csrf_protection' => true,
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
                        'users' => [
                            self::USERNAME => [
                                'password' => 'password',
                                'roles' => ['ROLE_USER'],
                            ],
                        ],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'in_memory',
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
                $fixtures . '/templates' => 'LayoutTest',
            ],
        ]);

        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components',
        ]);

        $container->extension('ux_icons', [
            'icon_dir' => $fixtures . '/icons',
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

        $services->set(TestMenuProvider::class)
            ->args([service('knp_menu.factory')])
            ->tag('knp_menu.provider');

        $services->set(MenuRuntime::class)
            ->args([service('knp_menu.menu_provider')])
            ->tag('twig.runtime');

        $services->set(MenuExtension::class)
            ->tag('twig.extension');

        // No application-wide defaults: the tests exercise LayoutOption's own defaults.
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
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The layouts only ever link to paths, so no routes are needed.
    }
}
