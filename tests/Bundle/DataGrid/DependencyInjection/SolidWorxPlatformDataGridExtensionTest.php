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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\DependencyInjection;

use Pentiminax\UX\DataTables\DataTablesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\DependencyInjection\SolidWorxPlatformDataGridExtension;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridDefaults;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SolidWorxPlatformDataGridExtension::class)]
final class SolidWorxPlatformDataGridExtensionTest extends TestCase
{
    public function testDefaultParametersAreSet(): void
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformDataGridExtension([])->load([], $container);

        self::assertSame('IS_AUTHENTICATED_FULLY', $container->getParameter('solidworx_platform_datagrid.security.ajax_access'));
        self::assertSame(25, $container->getParameter('solidworx_platform_datagrid.page_length'));
        self::assertSame([10, 25, 50, 100], $container->getParameter('solidworx_platform_datagrid.length_menu'));
        self::assertTrue($container->getParameter('solidworx_platform_datagrid.responsive'));
        self::assertTrue($container->getParameter('solidworx_platform_datagrid.column_control'));
        self::assertSame('table table-vcenter card-table', $container->getParameter('solidworx_platform_datagrid.table_class'));
        self::assertTrue($container->getParameter('solidworx_platform_datagrid.export.enabled'));
        self::assertSame(['csv', 'xlsx'], $container->getParameter('solidworx_platform_datagrid.export.formats'));
        self::assertTrue($container->getParameter('solidworx_platform_datagrid.edit_modal.enabled'));
    }

    public function testRawSectionOverridesDefaults(): void
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformDataGridExtension([
            'page_length' => 100,
        ])->load([], $container);

        self::assertSame(100, $container->getParameter('solidworx_platform_datagrid.page_length'));
    }

    public function testDataTablesDefaultsArePrepended(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DataTablesStubExtension());

        new SolidWorxPlatformDataGridExtension([
            'page_length' => 15,
        ])->prepend($container);

        $config = $container->getExtensionConfig('data_tables');

        $options = $config[0]['options'];
        self::assertIsArray($options);
        self::assertSame(15, $options['pageLength']);

        $editModal = $config[0]['edit_modal'];
        self::assertIsArray($editModal);
        self::assertSame('@DataTables/modal/bs5/edit_modal.html.twig', $editModal['template']);
    }

    public function testPrependIsSkippedWhenTheDataTablesExtensionIsAbsent(): void
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformDataGridExtension([])->prepend($container);

        self::assertSame([], $container->getExtensionConfig('data_tables'));
    }

    public function testAbstractDataGridIsAutoconfiguredWithDataGridDefaults(): void
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformDataGridExtension([])->load([], $container);

        $instanceOf = $container->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(AbstractDataGrid::class, $instanceOf);

        $definition = $instanceOf[AbstractDataGrid::class];
        self::assertInstanceOf(ChildDefinition::class, $definition);

        self::assertCount(1, $definition->getMethodCalls());

        [$method, $arguments] = $this->firstMethodCall($definition);
        self::assertSame('setDataGridDefaults', $method);
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(DataGridDefaults::class, (string) $arguments[0]);
    }

    /**
     * `Definition::getMethodCalls()` returns a bare `array`, so PHPStan sees
     * each entry as `mixed`. Narrow the one entry this test cares about to
     * its real shape instead of destructuring `mixed` directly.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function firstMethodCall(ChildDefinition $definition): array
    {
        /** @var array{0: string, 1: list<mixed>} */
        return $definition->getMethodCalls()[0];
    }

    /**
     * Pins the stub's alias to the real bundle's, rather than to a literal, so a future
     * upstream rename cannot make this whole test class pass while asserting nothing.
     */
    public function testStubExtensionAliasMatchesTheRealDataTablesBundle(): void
    {
        $extension = new DataTablesBundle()->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertSame($extension->getAlias(), new DataTablesStubExtension()->getAlias());
    }
}

final class DataTablesStubExtension extends \Symfony\Component\DependencyInjection\Extension\Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'data_tables';
    }
}
