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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\Legacy;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity\Client;

/**
 * Shares its short class name with {@see \SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid}
 * on purpose, to prove the renderer's repeat-render counter is keyed on the
 * short class name upstream derives the DOM id from, not the FQCN.
 */
#[AsDataTable(Client::class)]
final class ClientDataGrid extends AbstractDataGrid
{
}
