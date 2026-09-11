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

use SolidWorx\Platform\DataGridBundle\Column\DoctrineColumnFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The platform-wide grid defaults, injected into every {@see AbstractDataGrid}.
 *
 * A plain carrier rather than constructor arguments on the grid itself, so a
 * grid subclass keeps a zero-argument constructor.
 *
 * @phpstan-type ExportFormat 'csv'|'xlsx'
 */
final readonly class DataGridDefaults
{
    /**
     * @param list<int>          $lengthMenu
     * @param list<ExportFormat> $exportFormats
     */
    public function __construct(
        public DoctrineColumnFactory $columnFactory,
        #[Autowire(param: 'solidworx_platform_datagrid.page_length')]
        public int $pageLength,
        #[Autowire(param: 'solidworx_platform_datagrid.length_menu')]
        public array $lengthMenu,
        #[Autowire(param: 'solidworx_platform_datagrid.responsive')]
        public bool $responsive,
        #[Autowire(param: 'solidworx_platform_datagrid.column_control')]
        public bool $columnControl,
        #[Autowire(param: 'solidworx_platform_datagrid.table_class')]
        public string $tableClass,
        #[Autowire(param: 'solidworx_platform_datagrid.export.enabled')]
        public bool $exportEnabled,
        #[Autowire(param: 'solidworx_platform_datagrid.export.formats')]
        public array $exportFormats,
    ) {
    }
}
