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

namespace SolidWorx\Platform\DataGridBundle\Twig;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Decorates upstream's `datatables.twig_extension`, replacing its
 * `render_datatable()` Twig function with one that delegates to
 * {@see DataGridRenderer} instead of calling
 * {@see DataTablesExtension::renderDataTable()} directly.
 *
 * `DataTablesExtension::renderDataTable()` hardcodes the Stimulus controller
 * name and gives no way to override it through the public API, so it cannot
 * be pointed at the renamed controller by configuration alone. Decorating
 * (rather than removing) keeps `render_datatable()` working exactly as
 * upstream documents it, while guaranteeing every render goes through
 * {@see DataGridRenderer}'s post-processing.
 *
 * Symfony's `DecoratorServicePass` moves a decorated service's tags --
 * `twig.extension` included -- onto its decorator, so upstream's original
 * extension stops being registered with Twig once this class decorates it:
 * there is no duplicate `render_datatable` function.
 */
final class DataGridTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly DataGridRenderer $renderer,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_datatable', $this->renderDataTable(...), [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function renderDataTable(AbstractDataTable $table): string
    {
        return $this->renderer->render($table);
    }
}
