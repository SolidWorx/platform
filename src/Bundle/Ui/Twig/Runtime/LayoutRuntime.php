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

namespace SolidWorx\Platform\UiBundle\Twig\Runtime;

use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use SolidWorx\Platform\UiBundle\Layout\LayoutResolver;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Backs the `ui_layout()` and `ui_layout_resolve()` Twig functions.
 *
 * `ui_layout()` exists so the options have a real PHP signature. Twig matches named arguments
 * against parameter names ignoring case and underscores, so a template writes
 * `ui_layout(navbar_theme: 'dark')` and an IDE that resolves the Twig function to this method can
 * complete and type-check the argument list — which a bare `{% set layout = {...} %}` hash can
 * never offer. Passing an option that does not exist is a PHP error, not a silent no-op.
 */
final readonly class LayoutRuntime implements RuntimeExtensionInterface
{
    /**
     * Distinguishes "argument omitted" from "argument explicitly set to null" for the options
     * where null is itself a meaningful value.
     */
    private const string UNSET = "\0unset\0";

    public function __construct(
        private LayoutResolver $resolver,
    ) {
    }

    /**
     * Builds a validated, sparse set of layout options — only what the caller actually passed.
     *
     * @return array<string, bool|string|null>
     */
    public function layout(
        ?string $theme = self::UNSET,
        ?bool $fluid = null,
        ?bool $boxed = null,
        ?string $container = self::UNSET,
        ?bool $navbar = null,
        ?string $navbarTheme = self::UNSET,
        ?bool $navbarSticky = null,
        ?bool $navbarOverlap = null,
        ?string $navbarExpand = null,
        ?bool $sidebar = null,
        ?string $sidebarTheme = self::UNSET,
        ?string $sidebarPosition = null,
        ?bool $sidebarTransparent = null,
        ?string $sidebarExpand = null,
        ?bool $pageHeader = null,
        ?bool $footer = null,
        ?bool $centered = null,
    ): array {
        // For options where null is a real value, "omitted" is the UNSET sentinel; everywhere else
        // null cannot be a value, so null itself means "omitted".
        $nullable = [
            LayoutOption::Theme->value => $theme,
            LayoutOption::Container->value => $container,
            LayoutOption::NavbarTheme->value => $navbarTheme,
            LayoutOption::SidebarTheme->value => $sidebarTheme,
        ];

        $nonNullable = [
            LayoutOption::Fluid->value => $fluid,
            LayoutOption::Boxed->value => $boxed,
            LayoutOption::Navbar->value => $navbar,
            LayoutOption::NavbarSticky->value => $navbarSticky,
            LayoutOption::NavbarOverlap->value => $navbarOverlap,
            LayoutOption::NavbarExpand->value => $navbarExpand,
            LayoutOption::Sidebar->value => $sidebar,
            LayoutOption::SidebarPosition->value => $sidebarPosition,
            LayoutOption::SidebarTransparent->value => $sidebarTransparent,
            LayoutOption::SidebarExpand->value => $sidebarExpand,
            LayoutOption::PageHeader->value => $pageHeader,
            LayoutOption::Footer->value => $footer,
            LayoutOption::Centered->value => $centered,
        ];

        return $this->resolver->validate([
            ...array_filter($nullable, static fn (?string $value): bool => $value !== self::UNSET),
            ...array_filter($nonNullable, static fn (bool | string | null $value): bool => $value !== null),
        ]);
    }

    /**
     * Merges the defaults, the application configuration and the template's own options.
     *
     * Layouts call this once; templates never need to.
     *
     * @param array<array-key, mixed> $overrides
     *
     * @return array<string, bool|string|null>
     */
    public function resolve(array $overrides = []): array
    {
        return $this->resolver->resolve($overrides);
    }
}
