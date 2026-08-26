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
                ->arrayNode('layout')
                    ->info('Application-wide layout defaults; templates override them with {% set layout = {...} %}')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('theme')
                            ->info('Forces the colour scheme instead of following the user preference')
                            ->values([null, 'light', 'dark'])
                            ->defaultNull()
                        ->end()
                        ->booleanNode('fluid')
                            ->info('Use the full width of the viewport')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('boxed')
                            ->info('Constrain the page to a boxed width')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('navbar')
                            ->info('Render the top navigation bar')
                            ->defaultTrue()
                        ->end()
                        ->enumNode('navbar_theme')
                            ->info('Colour scheme of the top navigation bar')
                            ->values([null, 'light', 'dark'])
                            ->defaultNull()
                        ->end()
                        ->booleanNode('navbar_sticky')
                            ->info('Keep the top navigation bar visible while scrolling')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('navbar_overlap')
                            ->info('Let the page body overlap the top navigation bar')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('navbar_expand')
                            ->info('Breakpoint at which the top navigation bar expands')
                            ->values(['sm', 'md', 'lg', 'xl'])
                            ->defaultValue('md')
                        ->end()
                        ->booleanNode('sidebar')
                            ->info('Render the sidebar')
                            ->defaultTrue()
                        ->end()
                        ->enumNode('sidebar_theme')
                            ->info('Colour scheme of the sidebar')
                            ->values([null, 'light', 'dark'])
                            ->defaultValue('dark')
                        ->end()
                        ->enumNode('sidebar_position')
                            ->info('Side the sidebar sits on')
                            ->values(['start', 'end'])
                            ->defaultValue('start')
                        ->end()
                        ->booleanNode('sidebar_transparent')
                            ->info('Remove the sidebar background')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('sidebar_expand')
                            ->info('Breakpoint at which the sidebar expands')
                            ->values(['sm', 'md', 'lg', 'xl'])
                            ->defaultValue('lg')
                        ->end()
                        ->booleanNode('page_header')
                            ->info('Render the page header')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('footer')
                            ->info('Render the page footer')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $treeBuilder;
    }
}
