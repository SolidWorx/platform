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

namespace SolidWorx\Platform\UiBundle\Config;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigurationInterface;
use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @phpstan-type UiLayoutOptions array{
 *     theme: 'light'|'dark'|null,
 *     fluid: bool,
 *     boxed: bool,
 *     navbar: bool,
 *     navbar_theme: 'light'|'dark'|null,
 *     navbar_sticky: bool,
 *     navbar_overlap: bool,
 *     navbar_expand: 'sm'|'md'|'lg'|'xl',
 *     sidebar: bool,
 *     sidebar_theme: 'light'|'dark'|null,
 *     sidebar_position: 'start'|'end',
 *     sidebar_transparent: bool,
 *     sidebar_expand: 'sm'|'md'|'lg'|'xl',
 *     page_header: bool,
 *     footer: bool,
 * }
 * @phpstan-type UiLayoutTemplates array{app: string, condensed: string, clean: string}
 * @phpstan-type UiConfig array{
 *     icon_pack: string,
 *     templates: array{base: string, login: string, layouts: UiLayoutTemplates},
 *     layout: UiLayoutOptions,
 * }
 */
final class UiConfiguration implements PlatformConfigurationInterface
{
    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'ui';
    }

    #[Override]
    public function getTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ui');
        $root = $treeBuilder->getRootNode();

        // @formatter:off
        $root
            ->info('UI / presentation configuration')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('icon_pack')
                    ->info('The icon pack to use')
                    ->defaultValue('tabler')
                ->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base')
                            ->info('The base template')
                            ->defaultValue('@Ui/Layout/base.html.twig')
                        ->end()
                        ->scalarNode('login')
                            ->info('The standard login template')
                            ->defaultValue('@Ui/Security/login.html.twig')
                        ->end()
                        ->arrayNode('layouts')
                            ->info('The layouts pages extend, available in Twig as ui_layout_<name>')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('app')
                                    ->info('Sidebar + top navbar, for the signed-in application')
                                    ->defaultValue('@Ui/Layout/app.html.twig')
                                ->end()
                                ->scalarNode('condensed')
                                    ->info('Top navbar only, without a sidebar')
                                    ->defaultValue('@Ui/Layout/condensed.html.twig')
                                ->end()
                                ->scalarNode('clean')
                                    ->info('No navigation at all, for login / 2FA / error pages')
                                    ->defaultValue('@Ui/Layout/clean.html.twig')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->append($this->layoutNode())
            ->end();
        // @formatter:on

        return $treeBuilder;
    }

    /**
     * Built from {@see LayoutOption} so the configuration tree, the runtime defaults and the
     * `{% types %}` declarations in the layouts can never drift apart.
     */
    private function layoutNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('layout');

        $node
            ->info('Application-wide layout defaults; templates override them with {% set layout = {...} %}')
            ->addDefaultsIfNotSet();

        $children = $node->children();

        foreach (LayoutOption::configurable() as $option) {
            $allowedValues = $option->allowedValues();

            if ($allowedValues !== null) {
                $children
                    ->enumNode($option->value)
                        ->info($option->description())
                        ->values($allowedValues)
                        ->defaultValue($option->default())
                    ->end();

                continue;
            }

            $children
                ->booleanNode($option->value)
                    ->info($option->description())
                    ->defaultValue($option->default())
                ->end();
        }

        $children->end();

        return $node;
    }
}
