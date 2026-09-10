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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle;

use BadMethodCallException;
use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\ContainerLoader;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    /**
     * @param array<string, mixed> $rawConfig
     */
    #[DataProvider('dataGridEnabledProvider')]
    public function testIsDataGridEnabled(array $rawConfig, bool $expected): void
    {
        $kernel = new DataGridKernelFixture('test', false);

        // Reflected on the declaring class, not the fixture subclass: a private property is
        // not found by name when reflecting a child class in current PHP.
        $rawConfigProperty = new ReflectionClass(Kernel::class)->getProperty('rawConfig');
        $rawConfigProperty->setValue($kernel, $rawConfig);

        $isDataGridEnabled = new ReflectionMethod($kernel, 'isDataGridEnabled');

        self::assertSame($expected, $isDataGridEnabled->invoke($kernel));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function dataGridEnabledProvider(): iterable
    {
        yield 'section absent' => [[], true];
        yield 'section not an array' => [[
            'datagrid' => 'oops',
        ], true];
        yield 'enabled absent' => [[
            'datagrid' => [],
        ], true];
        yield 'enabled true' => [[
            'datagrid' => [
                'enabled' => true,
            ],
        ], true];
        yield 'enabled false' => [[
            'datagrid' => [
                'enabled' => false,
            ],
        ], false];
        yield 'enabled string "true"' => [[
            'datagrid' => [
                'enabled' => 'true',
            ],
        ], true];
        yield 'enabled string "false"' => [[
            'datagrid' => [
                'enabled' => 'false',
            ],
        ], false];
        yield 'enabled int 1' => [[
            'datagrid' => [
                'enabled' => 1,
            ],
        ], true];
    }

    /**
     * Kernel::configureRoutes() imports 'datatables.route_loader::loadRoutes' with type
     * 'service' when the grid is enabled, so a consuming app without the third-party Flex
     * recipe still gets the /datatables/ajax/* routes upstream's grid needs.
     *
     * That import cannot be exercised end to end here: RoutingConfigurator::import() is
     * declared `final`, so it cannot be mocked or spied on, and MicroKernelTrait's default
     * configureRoutes() (which Kernel::configureRoutes() calls first) requires a real
     * `config/routes` directory to resolve its glob imports without throwing
     * FileLocatorFileNotFoundException — this repository is a bundle library with no
     * `config/` directory at all. Fully exercising configureRoutes() would therefore mean
     * booting the whole application kernel, which also drags in SolidWorxPlatformBundle's
     * Doctrine/security/mailer wiring just to prove a one-line routing import.
     *
     * Instead, this drives the exact mechanism Symfony's real `routing.loader.container`
     * service uses for a `type: service` import — ContainerLoader (an ObjectLoader) reading
     * a container service by id and calling a named method on it — with the exact resource
     * string Kernel::configureRoutes() passes, proving it resolves to the real upstream
     * route loader and yields the eight ajax routes. isDataGridEnabled(), which gates the
     * import, already has full coverage above via testIsDataGridEnabled().
     */
    public function testTheDataGridRouteImportResolvesToTheEightAjaxRoutes(): void
    {
        $container = new ContainerBuilder();
        $container->set('datatables.route_loader', new RouteLoader());

        $loader = new ContainerLoader($container);

        self::assertTrue($loader->supports('datatables.route_loader::loadRoutes', 'service'));

        $routes = $loader->load('datatables.route_loader::loadRoutes', 'service');

        self::assertSame(
            [
                'ux_datatables_ajax_data',
                'ux_datatables_ajax_templates',
                'ux_datatables_ajax_edit',
                'ux_datatables_ajax_delete',
                'ux_datatables_ajax_edit_form',
                'ux_datatables_ajax_edit_form_submit',
                'ux_datatables_ajax_detail',
                'ux_datatables_ajax_export',
            ],
            array_keys(iterator_to_array($routes)),
        );
    }

    /**
     * RouteLoader has no __invoke(), only loadRoutes(); Symfony\Component\Routing\Loader\
     * ObjectLoader::load() defaults a bare service id (no "::method" suffix) to calling
     * __invoke(). Guards against Kernel::configureRoutes() regressing to the service id
     * alone, which would compile fine but throw at runtime on first render.
     */
    public function testTheDataGridRouteImportRequiresTheLoadRoutesMethodSuffix(): void
    {
        $container = new ContainerBuilder();
        $container->set('datatables.route_loader', new RouteLoader());

        $loader = new ContainerLoader($container);

        $this->expectException(BadMethodCallException::class);

        $loader->load('datatables.route_loader', 'service');
    }
}

/**
 * Minimal concrete subclass; {@see Kernel} has no unimplemented abstract members of its
 * own, but is itself declared abstract so consuming applications must name their own kernel.
 */
final class DataGridKernelFixture extends Kernel
{
}
