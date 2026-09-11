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
 * Renders one icon per cell, chosen by the cell's value.
 *
 * Server-rendered rather than using upstream's IconColumn, whose icon names
 * come from a Lucide-only enum resolved in the browser: the platform renders
 * through ux_icon() so grid icons match the rest of the Tabler UI.
 */
final class IconColumn extends TemplateColumn
{
    private const string TEMPLATE = '@DataGrid/columns/icon.html.twig';

    /**
     * @var array<string, string>
     */
    private array $icons = [];

    private ?string $fallbackIcon = null;

    #[Override]
    public static function new(string $name, string $title = ''): static
    {
        $column = parent::new($name, $title);
        $column->applyTemplate();

        return $column;
    }

    /**
     * @param array<string, string> $icons cell value => ux-icons name, e.g. ['active' => 'tabler:circle-check']
     */
    public function icons(array $icons): static
    {
        $this->icons = $icons;

        return $this->applyTemplate();
    }

    /**
     * Icon used when the cell value matches no entry in the map.
     */
    public function fallbackIcon(?string $icon): static
    {
        $this->fallbackIcon = $icon;

        return $this->applyTemplate();
    }

    private function applyTemplate(): static
    {
        return $this->setTemplate(self::TEMPLATE, [
            'icons' => $this->icons,
            'fallbackIcon' => $this->fallbackIcon,
        ]);
    }
}
