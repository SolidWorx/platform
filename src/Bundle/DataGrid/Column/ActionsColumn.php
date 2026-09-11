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

namespace SolidWorx\Platform\DataGridBundle\Column;

use Override;
use Pentiminax\UX\DataTables\Column\TemplateColumn;

/**
 * Renders row action buttons server-side, with Tabler icons.
 *
 * The upstream Stimulus controller delegates on attributes it reads from the
 * DOM — it matches `[data-action-type]` and reads `data-id` — so buttons
 * rendered here behave exactly like upstream's ActionColumn ones without any
 * JavaScript change.
 *
 * Per-row Ajax actions are NOT supported here: their CSRF token lives in the
 * row's `__ux_datatables_actions` key, which RowProcessingPipeline populates
 * only after template columns have already rendered. Use upstream's
 * ActionColumn for those.
 */
final class ActionsColumn extends TemplateColumn
{
    private const string TEMPLATE = '@DataGrid/columns/actions.html.twig';

    /**
     * @var list<array{type: string, icon: string, label: string, route: string|null, routeParameter: string|null, confirm: string|null}>
     */
    private array $actions = [];

    private string $idField = 'id';

    #[Override]
    public static function new(string $name = 'actions', string $title = ''): static
    {
        $column = parent::new($name, $title);
        $column->applyTemplate();

        return $column;
    }

    /**
     * Opens the inline edit modal for the row.
     */
    public function edit(string $icon = 'tabler:pencil', string $label = 'Edit'): static
    {
        return $this->addAction('EDIT', $icon, $label);
    }

    /**
     * Deletes the row. This column renders its own markup, so it does not
     * reproduce upstream's `mutationsEnabled` disabling of the delete
     * button; the server still validates CSRF, so a delete attempted
     * without a session fails server-side rather than being disabled
     * client-side.
     */
    public function delete(string $icon = 'tabler:trash', string $label = 'Delete', ?string $confirm = null): static
    {
        return $this->addAction('DELETE', $icon, $label, confirm: $confirm);
    }

    /**
     * A plain link, whose URL is generated per row from the identifier.
     */
    public function link(string $route, string $icon, string $label, string $routeParameter = 'id'): static
    {
        return $this->addAction('CUSTOM', $icon, $label, route: $route, routeParameter: $routeParameter);
    }

    /**
     * The row key holding the identifier passed to the controller and to route
     * generation. Defaults to `id`.
     */
    public function identifiedBy(string $field): static
    {
        $this->idField = $field;

        return $this->applyTemplate();
    }

    private function addAction(
        string $type,
        string $icon,
        string $label,
        ?string $route = null,
        ?string $routeParameter = null,
        ?string $confirm = null,
    ): static {
        $this->actions[] = [
            'type' => $type,
            'icon' => $icon,
            'label' => $label,
            'route' => $route,
            'routeParameter' => $routeParameter,
            'confirm' => $confirm,
        ];

        return $this->applyTemplate();
    }

    private function applyTemplate(): static
    {
        return $this->setTemplate(self::TEMPLATE, [
            'actions' => $this->actions,
            'idField' => $this->idField,
        ]);
    }
}
