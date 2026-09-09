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

use Override;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity\Client;

#[AsDataTable(Client::class)]
final class SecuredDataGrid extends AbstractDataGrid
{
    #[Override]
    public function getSecurityAttribute(): string
    {
        return 'ROLE_ADMIN';
    }
}
