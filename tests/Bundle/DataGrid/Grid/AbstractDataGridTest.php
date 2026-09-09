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

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Override;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Column\DoctrineColumnFactory;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridDefaults;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use function array_map;
use function iterator_to_array;

/**
 * `DoctrineColumnFactory` is `final readonly` (see Task 4), so PHPUnit cannot
 * mock it -- doing so errors rather than fails. Build a real instance over an
 * in-memory SQLite `EntityManager` instead, the same way
 * {@see \SolidWorx\Platform\Tests\Bundle\DataGrid\Column\DoctrineColumnFactoryTest}
 * does, and assert against the columns it really produces for the `Client`
 * fixture.
 */
#[CoversClass(AbstractDataGrid::class)]
#[CoversClass(DataGridDefaults::class)]
final class AbstractDataGridTest extends TestCase
{
    private DoctrineColumnFactory $columnFactory;

    #[Override]
    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->columnFactory = new DoctrineColumnFactory(new EntityManager($connection, $config));
    }

    public function testTablesAreServerSideByDefault(): void
    {
        $table = $this->configure();

        self::assertTrue($table->isServerSide());
    }

    public function testStyleFrameworkIsBootstrap5(): void
    {
        self::assertSame('bs5', $this->configure()->getOption('styleFramework'));
    }

    public function testPageLengthAndLengthMenuComeFromTheDefaults(): void
    {
        $table = $this->configure();

        self::assertSame(25, $table->getOption('pageLength'));
        self::assertSame([10, 25, 50, 100], $table->getOption('lengthMenu'));
    }

    public function testTableClassIsAppliedAsAnAttribute(): void
    {
        self::assertSame(
            [
                'class' => 'table table-vcenter card-table',
            ],
            $this->configure()->getAttributes(),
        );
    }

    public function testColumnsFallBackToDoctrineDetection(): void
    {
        $grid = $this->createGrid();

        $columns = iterator_to_array($grid->configureColumns());

        self::assertNotSame([], $columns, 'The fallback to Doctrine metadata was not taken.');

        $names = array_map(static fn (ColumnInterface $column): string => $column->getName(), $columns);

        self::assertContains('companyName', $names);
    }

    public function testGetSecurityAttributeDefaultsToNull(): void
    {
        self::assertNull($this->createGrid()->getSecurityAttribute());
    }

    private function createGrid(): ClientDataGrid
    {
        $grid = new ClientDataGrid();
        $grid->setDataGridDefaults(new DataGridDefaults(
            columnFactory: $this->columnFactory,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            columnControl: true,
            tableClass: 'table table-vcenter card-table',
            exportEnabled: true,
            exportFormats: ['csv', 'xlsx'],
        ));

        return $grid;
    }

    private function configure(): DataTable
    {
        return $this->createGrid()->configureDataTable(new DataTable('ClientDataGrid'));
    }
}
