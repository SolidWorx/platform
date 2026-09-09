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

namespace SolidWorx\Platform\DataGridBundle\Config;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @phpstan-type DataGridConfig array{
 *     enabled: bool,
 *     page_length: int,
 *     length_menu: list<int>,
 *     responsive: bool,
 *     column_control: bool,
 *     table_class: string,
 *     security: array{ajax_access: string},
 *     export: array{enabled: bool, formats: list<'csv'|'xlsx'>},
 *     edit_modal: array{enabled: bool},
 * }
 */
final class DataGridConfiguration implements PlatformConfigurationInterface
{
    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'datagrid';
    }

    #[Override]
    public function getTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('datagrid');

        // @formatter:off
        $treeBuilder->getRootNode()
            ->info('Data grid configuration')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Register the data grid bundle; false removes it and its dependency entirely')
                    ->defaultTrue()
                ->end()
                ->integerNode('page_length')
                    ->info('Rows shown per page')
                    ->min(1)
                    ->defaultValue(25)
                ->end()
                ->arrayNode('length_menu')
                    ->info('Page size options offered to the user')
                    ->integerPrototype()->min(1)->end()
                    ->defaultValue([10, 25, 50, 100])
                ->end()
                ->booleanNode('responsive')
                    ->info('Collapse overflowing columns into an expandable child row')
                    ->defaultTrue()
                ->end()
                ->booleanNode('column_control')
                    ->info('Show per-column ordering and search controls in the header')
                    ->defaultTrue()
                ->end()
                ->scalarNode('table_class')
                    ->info('CSS classes applied to the rendered table element')
                    ->cannotBeEmpty()
                    ->defaultValue('table table-vcenter card-table')
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('ajax_access')
                            ->info('Attribute required to reach the /datatables/ajax/* routes')
                            ->cannotBeEmpty()
                            ->defaultValue('IS_AUTHENTICATED_FULLY')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('export')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Add server-side export buttons to every grid')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('formats')
                            ->info('Export formats offered')
                            ->enumPrototype()->values(['csv', 'xlsx'])->end()
                            ->defaultValue(['csv', 'xlsx'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('edit_modal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Allow grids to render the inline edit modal')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $treeBuilder;
    }
}
