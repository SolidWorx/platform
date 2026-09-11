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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Config\Builder\DataGridConfigBuilder;
use SolidWorx\Platform\DataGridBundle\Config\DataGridConfiguration;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(DataGridConfigBuilder::class)]
final class DataGridConfigBuilderTest extends TestCase
{
    public function testEmptyBuilderProducesAnEmptyArray(): void
    {
        self::assertSame([], DataGridConfigBuilder::create()->build());
    }

    public function testBuiltValuesAreSet(): void
    {
        $config = DataGridConfigBuilder::create()
            ->disabled()
            ->pageLength(50)
            ->lengthMenu([25, 50])
            ->tableClass('table')
            ->ajaxAccess('ROLE_ADMIN')
            ->exportFormats(['csv'])
            ->build();

        self::assertSame([
            'enabled' => false,
            'page_length' => 50,
            'length_menu' => [25, 50],
            'table_class' => 'table',
            'security' => [
                'ajax_access' => 'ROLE_ADMIN',
            ],
            'export' => [
                'formats' => ['csv'],
            ],
        ], $config);
    }

    public function testOutputIsAcceptedByTheConfigurationTree(): void
    {
        $built = DataGridConfigBuilder::create()->pageLength(10)->build();

        $processed = new Processor()->process(
            new DataGridConfiguration()->getTreeBuilder()->buildTree(),
            [$built],
        );

        self::assertSame(10, $processed['page_length']);
    }
}
