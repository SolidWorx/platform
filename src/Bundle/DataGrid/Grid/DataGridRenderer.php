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
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use Symfony\Contracts\Service\ResetInterface;
use function preg_replace_callback;
use function sprintf;
use function strrpos;
use function substr;

/**
 * Renders a grid, giving repeat renders of the same grid on one page a unique
 * element id.
 *
 * `DataTable::$id` is readonly and derived from the grid's class name, so
 * rendering the same grid twice on one page would emit the same DOM id
 * twice. Reading that id back off the grid via `getDataTable()->getId()`
 * would force upstream's `AbstractDataTable::initialize()` to run, which
 * calls `AbstractDataGrid::defaults()` — and that throws whenever the grid
 * has no {@see DataGridDefaults} injected. So the id is instead read back out
 * of the markup {@see DataTablesExtension::renderDataTable()} already
 * produced, and the repeat-render counter is keyed on the grid's short class
 * name — the same derivation upstream uses for the id itself, so two grids
 * with the same short name in different namespaces are correctly treated as
 * colliding, even though their FQCNs differ. Deriving that name needs no
 * initialised grid either.
 */
final class DataGridRenderer implements ResetInterface
{
    /**
     * @var array<string, positive-int>
     */
    private array $renderCounts = [];

    public function __construct(
        private readonly DataTablesExtension $dataTables,
    ) {
    }

    public function render(AbstractDataGrid $grid): string
    {
        $html = $this->dataTables->renderDataTable($grid);

        $key = $this->shortClassName($grid);
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

    #[Override]
    public function reset(): void
    {
        $this->renderCounts = [];
    }

    /**
     * Replicates upstream's own id derivation (`AbstractDataTable::getClassName()`),
     * which uses the short class name, not the FQCN — so two grids with the
     * same short name in different namespaces are treated as one collision
     * key, matching the single DOM id upstream would give them both.
     */
    private function shortClassName(AbstractDataGrid $grid): string
    {
        $class = $grid::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
