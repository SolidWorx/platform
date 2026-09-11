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

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use SolidWorx\Platform\DataGridBundle\Twig\DataGridTwigExtension;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use function is_string;
use function sprintf;

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

    /**
     * The `render_datatable` Twig function is bound directly to
     * `renderDataTable(...)` (see `getFunctions()`); calling that bound
     * callable -- rather than the method directly -- is what a Twig template
     * actually invokes, so this proves `{{ render_datatable(table, {...}) }}`
     * itself keeps working, not just the underlying method.
     */
    public function testAttributesPassedToTheTwigFunctionReachTheRenderedMarkup(): void
    {
        $dataTables = self::createStub(DataTablesExtension::class);
        $dataTables->method('renderDataTable')->willReturnCallback(self::echoAttributesIntoMarkup(...));

        $extension = new DataGridTwigExtension(new DataGridRenderer($dataTables));
        $renderDatatable = $extension->getFunctions()[0]->getCallable();

        self::assertIsCallable($renderDatatable);

        $html = $renderDatatable(new ClientDataGrid(), [
            'data-extra' => 'from-caller',
        ]);

        self::assertIsString($html);
        self::assertStringContainsString('data-extra="from-caller"', $html);
    }

    private function rendererStub(string $html): DataGridRenderer
    {
        $extension = self::createStub(DataTablesExtension::class);
        $extension->method('renderDataTable')->willReturn($html);

        return new DataGridRenderer($extension);
    }

    /**
     * A stand-in for upstream's real `renderDataTable()`, which folds
     * `$attributes` into the returned markup -- simplified, but enough to
     * prove attributes given to the Twig function reach it.
     *
     * @param array<string, mixed> $attributes
     */
    private static function echoAttributesIntoMarkup(AbstractDataTable $table, array $attributes = []): string
    {
        $extra = $attributes['data-extra'] ?? '';

        return sprintf('<table id="ClientDataGrid" data-extra="%s"></table>', is_string($extra) ? $extra : '');
    }
}
