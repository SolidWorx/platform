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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Exception\UnknownDataGridException;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRegistry;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(DataGridRegistry::class)]
#[CoversClass(UnknownDataGridException::class)]
final class DataGridRegistryTest extends TestCase
{
    public function testResolvesAGridByName(): void
    {
        $grid = new ClientDataGrid();

        $registry = new DataGridRegistry(
            new ServiceLocator([
                'client' => static fn (): ClientDataGrid => $grid,
            ]),
        );

        self::assertSame($grid, $registry->get('client'));
    }

    public function testNamesListsEveryRegisteredGrid(): void
    {
        $registry = new DataGridRegistry(
            new ServiceLocator([
                'client' => static fn (): ClientDataGrid => new ClientDataGrid(),
                'invoice' => static fn (): ClientDataGrid => new ClientDataGrid(),
            ]),
        );

        self::assertSame(['client', 'invoice'], $registry->names());
    }

    public function testUnknownNameThrowsAndListsTheKnownOnes(): void
    {
        $registry = new DataGridRegistry(
            new ServiceLocator([
                'client' => static fn (): ClientDataGrid => new ClientDataGrid(),
            ]),
        );

        $this->expectException(UnknownDataGridException::class);
        $this->expectExceptionMessageIsOrContains('No data grid named "missing" is registered. Registered grids: "client".');

        $registry->get('missing');
    }
}
