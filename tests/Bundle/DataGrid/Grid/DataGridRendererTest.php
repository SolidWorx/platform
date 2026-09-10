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
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\Legacy\ClientDataGrid as LegacyClientDataGrid;
use function sprintf;

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
        // A second, raw id="…" occurrence after the table's own id — e.g. a
        // nested element upstream's markup happens to carry. If the rewrite
        // were not limited to the first occurrence, this decoy would also be
        // rewritten to "decoy-2".
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="x"><span id="decoy"></span></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);

        $html = $renderer->render($grid);

        self::assertStringContainsString('id="ClientDataGrid-2"', $html);
        self::assertStringContainsString('id="decoy"', $html);
        self::assertStringNotContainsString('id="decoy-2"', $html);
    }

    public function testTwoGridsWithTheSameShortClassNameInDifferentNamespacesShareTheCounter(): void
    {
        // Upstream derives the DOM id from the short class name (see
        // AbstractDataTable::getClassName()), so ClientDataGrid and
        // Legacy\ClientDataGrid would both render id="ClientDataGrid" even
        // though their FQCNs differ. The counter must key on that same short
        // name, or this collision goes undetected.
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="x"></table>');

        $renderer->render(new ClientDataGrid());

        self::assertStringContainsString(
            'id="ClientDataGrid-2"',
            $renderer->render(new LegacyClientDataGrid()),
        );
    }

    public function testMarkupWithoutAnIdIsReturnedUnchangedRatherThanThrowing(): void
    {
        $renderer = $this->renderer('<table data-controller="x"></table>');
        $grid = new ClientDataGrid();

        $renderer->render($grid);

        self::assertSame('<table data-controller="x"></table>', $renderer->render($grid));
    }

    public function testStimulusControllerIdentifierIsRewrittenToTheShortName(): void
    {
        // Upstream hardcodes this long, slash-derived identifier with no way
        // to override it; assets/core.ts registers the controller under the
        // short "datatable" name instead, so render() must rewrite it.
        $renderer = $this->renderer('<table id="ClientDataGrid" data-controller="pentiminax--ux-datatables--datatable"></table>');

        $html = $renderer->render(new ClientDataGrid());

        self::assertStringContainsString('data-controller="datatable"', $html);
        self::assertStringNotContainsString('pentiminax--ux-datatables--datatable', $html);
    }

    public function testStimulusValueAttributeIdentifierIsRewrittenToo(): void
    {
        // StimulusAttributes::addController() emits "data-<controller>-<key>-value"
        // for each Stimulus value; the payload attribute carries the long
        // identifier as well, and must be rewritten alongside data-controller.
        $renderer = $this->renderer(
            '<table id="ClientDataGrid" data-controller="pentiminax--ux-datatables--datatable" data-pentiminax--ux-datatables--datatable-view-value="{}"></table>',
        );

        $html = $renderer->render(new ClientDataGrid());

        self::assertStringContainsString('data-datatable-view-value="{}"', $html);
        self::assertStringNotContainsString('data-pentiminax--ux-datatables--datatable-view-value', $html);
    }

    public function testViewValuePayloadContainingTheIdentifierTextIsNotCorrupted(): void
    {
        // The view-value JSON payload is attacker-adjacent data -- arbitrary
        // row and column content -- so the rewrite must never touch text
        // inside it, even when that text happens to spell out the
        // identifier itself.
        $payload = '{&quot;columns&quot;:[{&quot;name&quot;:&quot;pentiminax--ux-datatables--datatable&quot;}]}';
        $renderer = $this->renderer(sprintf(
            '<table id="ClientDataGrid" data-controller="pentiminax--ux-datatables--datatable" data-pentiminax--ux-datatables--datatable-view-value="%s"></table>',
            $payload,
        ));

        $html = $renderer->render(new ClientDataGrid());

        self::assertStringContainsString($payload, $html);
    }

    private function renderer(string $html): DataGridRenderer
    {
        $extension = self::createStub(DataTablesExtension::class);
        $extension->method('renderDataTable')->willReturn($html);

        return new DataGridRenderer($extension);
    }
}
