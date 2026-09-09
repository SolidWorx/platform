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

use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;

/**
 * `$extension` is a stub fed fixed markup, never the real
 * `DataTablesExtension::renderDataTable()`, so `render()` never touches
 * `AbstractDataTable::initialize()` — the grid it is given needs no
 * {@see \SolidWorx\Platform\DataGridBundle\Grid\DataGridDefaults} injected.
 */
#[CoversClass(DataGridRenderer::class)]
final class DataGridRendererTest extends TestCase
{
    public function testFirstRenderIsUnchanged(): void
    {
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="x"></table>');

        self::assertSame(
            '<table id="ClientDataGrid" data-controller="x"></table>',
            $renderer->render(new ClientDataGrid()),
        );
    }

    public function testSecondRenderOfTheSameGridGetsAUniqueId(): void
    {
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="x"></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);

        self::assertStringContainsString('id="ClientDataGrid-2"', $renderer->render($grid));
    }

    public function testResetClearsTheCounter(): void
    {
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="x"></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);
        $renderer->reset();

        self::assertStringContainsString('id="ClientDataGrid"', $renderer->render($grid));
    }

    public function testOnlyTheFirstIdOccurrenceIsRewritten(): void
    {
        $renderer = $this->renderer('<table id="ClientDataGrid" data-x="id=&quot;not-the-id&quot;"></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);

        $html = $renderer->render($grid);

        self::assertStringContainsString('id="ClientDataGrid-2"', $html);
        self::assertStringContainsString('data-x="id=&quot;not-the-id&quot;"', $html);
    }

    public function testMarkupWithoutAnIdIsReturnedUnchangedRatherThanThrowing(): void
    {
        $renderer = $this->renderer('<table data-controller="x"></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);

        self::assertSame('<table data-controller="x"></table>', $renderer->render($grid));
    }

    public function testStimulusIdentifierMatchesTheRegisteredController(): void
    {
        // assets/core.ts registers this exact identifier; if the string here
        // changes, that registration must change with it.
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="pentiminax--ux-datatables--datatable"></table>');

        self::assertStringContainsString(
            'data-controller="pentiminax--ux-datatables--datatable"',
            $renderer->render(new ClientDataGrid()),
        );
    }

    private function renderer(string $html): DataGridRenderer
    {
        $extension = self::createStub(DataTablesExtension::class);
        $extension->method('renderDataTable')->willReturn($html);

        return new DataGridRenderer($extension);
    }
}
