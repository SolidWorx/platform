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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity\Client;

/**
 * A distinct class from {@see NamedDataGrid} that deliberately collides with
 * it on name, so a duplicate-name test can prove the compiler pass's
 * exception message names both offending classes rather than the same one
 * twice.
 */
#[AsDataTable(Client::class)]
final class DuplicateNamedDataGrid extends AbstractDataGrid
{
    public const string NAME = 'overridden';
}
