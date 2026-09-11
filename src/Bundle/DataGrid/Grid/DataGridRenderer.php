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

namespace SolidWorx\Platform\DataGridBundle\Grid;

use Override;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use Symfony\Contracts\Service\ResetInterface;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use function strrpos;
use function substr;

/**
 * Renders a data table, giving repeat renders of the same one on one page a
 * unique element id.
 *
 * `DataTable::$id` is readonly and derived from the table's class name, so
 * rendering the same table twice on one page would emit the same DOM id
 * twice. Reading that id back off the table via `getDataTable()->getId()`
 * would force upstream's `AbstractDataTable::initialize()` to run, which for
 * an {@see AbstractDataGrid} calls `AbstractDataGrid::defaults()` — and that
 * throws whenever the grid has no {@see DataGridDefaults} injected. So the id
 * is instead read back out of the markup
 * {@see DataTablesExtension::renderDataTable()} already produced, and the
 * repeat-render counter is keyed on the table's short class name — the same
 * derivation upstream uses for the id itself, so two tables with the same
 * short name in different namespaces are correctly treated as colliding,
 * even though their FQCNs differ. Deriving that name needs no initialised
 * table either.
 */
final class DataGridRenderer implements ResetInterface
{
    /**
     * Upstream hardcodes the Stimulus controller name
     * `@pentiminax/ux-datatables/datatable` in
     * {@see DataTablesExtension::renderDataTable()}, with no way to override
     * it through the public API. This is that name after
     * `StimulusAttributes::normalizeControllerName()` turns it into its HTML
     * form (leading "@" stripped, "/" becomes "--"). That method is private,
     * so the normalised form is hardcoded here rather than derived at
     * runtime.
     */
    private const string STIMULUS_CONTROLLER_IDENTIFIER = 'pentiminax--ux-datatables--datatable';

    private const string STIMULUS_CONTROLLER_SHORT_NAME = 'datatable';

    /**
     * @var array<string, positive-int>
     */
    private array $renderCounts = [];

    public function __construct(
        private readonly DataTablesExtension $dataTables,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes forwarded to upstream's
     *                                          `DataTablesExtension::renderDataTable()` unchanged, so
     *                                          `{{ render_datatable(table, {class: 'my-table'}) }}` keeps working
     *                                          through the decorator.
     */
    public function render(AbstractDataTable $table, array $attributes = []): string
    {
        $html = $this->rewriteControllerIdentifier($this->dataTables->renderDataTable($table, $attributes));

        $key = $this->shortClassName($table);
        $count = ($this->renderCounts[$key] ?? 0) + 1;
        $this->renderCounts[$key] = $count;

        if ($count === 1) {
            return $html;
        }

        // Rewrite only the first id="…" occurrence — the table element's own
        // id. If upstream's markup carries none, degrade to returning it
        // unchanged rather than throwing.
        return preg_replace_callback(
            '/id="([^"]*)"/',
            /**
             * @param array<int, string> $matches
             */
            static fn (array $matches): string => sprintf('id="%s-%d"', $matches[1], $count),
            $html,
            1,
        ) ?? $html;
    }

    /**
     * Rewrites upstream's long, slash-derived Stimulus identifier down to the
     * short `datatable` name {@see assets/core.ts} registers the controller
     * under, in both attribute shapes it appears in:
     *
     *  - `data-controller="…pentiminax--ux-datatables--datatable…"`
     *  - `data-pentiminax--ux-datatables--datatable-*` (e.g. the
     *    `-view-value` payload {@see \Symfony\UX\StimulusBundle\Dto\StimulusAttributes::addController()}
     *    emits)
     *
     * Deliberately scoped to those two shapes rather than a blanket string
     * replacement: the JSON `…-view-value` payload is attacker-adjacent data
     * (it can carry arbitrary row/column content) and may legitimately
     * contain the identifier text, which must survive untouched.
     */
    private function rewriteControllerIdentifier(string $html): string
    {
        $html = preg_replace_callback(
            '/data-controller="([^"]*)"/',
            /**
             * @param array<int, string> $matches
             */
            static fn (array $matches): string => sprintf(
                'data-controller="%s"',
                str_replace(self::STIMULUS_CONTROLLER_IDENTIFIER, self::STIMULUS_CONTROLLER_SHORT_NAME, $matches[1]),
            ),
            $html,
        ) ?? $html;

        // Bounded to the attribute-name position: the identifier must sit
        // directly after `data-` and be immediately followed by more
        // attribute-name characters and `="`. `StimulusAttributes::addController()`
        // appends `-<key>-value` (key via its kebab-casing `normalizeKeyName()`,
        // lowercase letters/digits/hyphens only), `-<key>-class` (same
        // normalisation) and `-<outlet>-outlet` (via `normalizeControllerName()`,
        // which does not lowercase, so an outlet name can carry uppercase
        // letters) -- hence the mixed-case class below. A blanket
        // `str_replace()` here would also match the identifier wherever it
        // appears inside the escaped JSON `-view-value` payload, corrupting
        // attacker-adjacent row/column content.
        return preg_replace(
            '/data-' . preg_quote(self::STIMULUS_CONTROLLER_IDENTIFIER, '/') . '-([a-zA-Z0-9-]+)="/',
            'data-' . self::STIMULUS_CONTROLLER_SHORT_NAME . '-$1="',
            $html,
        ) ?? $html;
    }

    #[Override]
    public function reset(): void
    {
        $this->renderCounts = [];
    }

    /**
     * Replicates upstream's own id derivation (`AbstractDataTable::getClassName()`),
     * which uses the short class name, not the FQCN — so two tables with the
     * same short name in different namespaces are treated as one collision
     * key, matching the single DOM id upstream would give them both.
     */
    private function shortClassName(AbstractDataTable $table): string
    {
        $class = $table::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
