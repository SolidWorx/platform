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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\DependencyInjection\SolidWorxPlatformDataGridExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        self::assertTrue($container->getParameter('solidworx_platform_datagrid.export.enabled'));
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

        $config = $container->getExtensionConfig('datatables');

        self::assertSame(15, $config[0]['options']['pageLength']);
        self::assertSame(
            '@DataTables/modal/bs5/edit_modal.html.twig',
            $config[0]['edit_modal']['template'],
        );
    }

    public function testPrependIsSkippedWhenTheDataTablesExtensionIsAbsent(): void
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformDataGridExtension([])->prepend($container);

        self::assertSame([], $container->getExtensionConfig('datatables'));
    }
}

final class DataTablesStubExtension extends \Symfony\Component\DependencyInjection\Extension\Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'datatables';
    }
}
