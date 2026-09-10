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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Twig;

use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use SolidWorx\Platform\DataGridBundle\Twig\DataGridTwigExtension;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;

#[CoversClass(DataGridTwigExtension::class)]
final class DataGridTwigExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersExactlyOneRenderDatatableFunction(): void
    {
        $extension = new DataGridTwigExtension($this->rendererStub('<table id="ClientDataGrid"></table>'));

        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('render_datatable', $functions[0]->getName());
    }

    /**
     * `DataGridRenderer` is final and cannot be doubled, so this compares
     * against a second, independently constructed renderer over the same
     * fixed markup rather than mocking the collaborator: both are "first
     * renders" of an equivalent grid, so their output -- id and rewritten
     * Stimulus identifier included -- must match if, and only if,
     * `renderDataTable()` truly delegates to `DataGridRenderer::render()`
     * rather than reimplementing or bypassing it.
     */
    public function testRenderDatatableDelegatesToDataGridRenderer(): void
    {
        $html = '<table id="ClientDataGrid" data-controller="pentiminax--ux-datatables--datatable"></table>';

        $directRenderer = $this->rendererStub($html);
        $extension = new DataGridTwigExtension($this->rendererStub($html));

        $grid = new ClientDataGrid();

        self::assertSame($directRenderer->render($grid), $extension->renderDataTable($grid));
    }

    private function rendererStub(string $html): DataGridRenderer
    {
        $extension = self::createStub(DataTablesExtension::class);
        $extension->method('renderDataTable')->willReturn($html);

        return new DataGridRenderer($extension);
    }
}
