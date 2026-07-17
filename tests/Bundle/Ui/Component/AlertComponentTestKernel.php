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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Component;

use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function dirname;
use function sys_get_temp_dir;

/**
 * Minimal, self-contained kernel used to render the shared {@see Ui:Alert} Twig component in isolation.
 *
 * The platform ships an abstract kernel that consuming applications extend; this library has no
 * bootable application of its own, so this kernel wires only the bundles required to render an
 * anonymous Twig component: Twig + the html/cva helpers, the UX component runtime and UX icons.
 */
final class AlertComponentTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<Bundle>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new TwigComponentBundle();
        yield new UXIconsBundle();
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_alert_component_test/cache/' . $this->environment;
    }

    #[Override]
    public function getBuildDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_alert_component_test/build/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_alert_component_test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'alert-component-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
        ]);

        $container->extension('twig', [
            'paths' => [
                dirname(__DIR__, 4) . '/src/Bundle/Ui/templates' => 'Ui',
            ],
        ]);

        // Anonymous components resolve against the default `components` directory; `Ui:Alert`
        // is then found at `@Ui/components/Alert.html.twig` via the registered `@Ui` namespace.
        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No routes are required to render the component.
    }
}
