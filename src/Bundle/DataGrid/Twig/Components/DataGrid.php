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

namespace SolidWorx\Platform\DataGridBundle\Twig\Components;

use SolidWorx\Platform\DataGridBundle\Grid\DataGridRegistry;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * `<twig:Platform:DataGrid name="client" />` resolves the named grid from the
 * {@see DataGridRegistry} and renders it, so a page needs no controller
 * wiring at all.
 */
#[AsTwigComponent(name: 'Platform:DataGrid', template: '@DataGrid/components/data_grid.html.twig')]
final class DataGrid
{
    /**
     * The registered grid name, e.g. "client" for a `ClientDataGrid`.
     */
    public string $name;

    public function __construct(
        private readonly DataGridRegistry $registry,
        private readonly DataGridRenderer $renderer,
    ) {
    }

    public function render(): string
    {
        return $this->renderer->render($this->registry->get($this->name));
    }
}
