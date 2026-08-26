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

namespace SolidWorx\Platform\UiBundle\Layout;

use function array_column;
use function array_map;
use function in_array;
use function is_bool;
use function is_string;

/**
 * The single source of truth for the layout options.
 *
 * Everything that needs to know about them reads it from here: the `platform.ui.layout`
 * configuration tree, the `ui_layout()` / `ui_layout_resolve()` Twig functions, and the
 * `{% types %}` declarations in the layouts. Adding an option is a single case here.
 */
enum LayoutOption: string
{
    case Theme = 'theme';

    case Fluid = 'fluid';

    case Boxed = 'boxed';

    case Container = 'container';

    case Navbar = 'navbar';

    case NavbarTheme = 'navbar_theme';

    case NavbarSticky = 'navbar_sticky';

    case NavbarOverlap = 'navbar_overlap';

    case NavbarExpand = 'navbar_expand';

    case Sidebar = 'sidebar';

    case SidebarTheme = 'sidebar_theme';

    case SidebarPosition = 'sidebar_position';

    case SidebarTransparent = 'sidebar_transparent';

    case SidebarExpand = 'sidebar_expand';

    case PageHeader = 'page_header';

    case Footer = 'footer';

    case Centered = 'centered';

    /**
     * The value used when neither the application nor the template says otherwise.
     */
    public function default(): bool | string | null
    {
        return match ($this) {
            self::SidebarTheme => 'dark',
            self::NavbarExpand => 'md',
            self::SidebarExpand => 'lg',
            self::SidebarPosition => 'start',
            self::Navbar, self::Sidebar, self::PageHeader, self::Footer => true,
            self::Fluid, self::Boxed, self::NavbarSticky, self::NavbarOverlap,
            self::SidebarTransparent, self::Centered => false,
            self::Theme, self::NavbarTheme, self::Container => null,
        };
    }

    /**
     * The values this option accepts, or null when it is a boolean or a free-form string.
     *
     * @return list<string|null>|null
     */
    public function allowedValues(): ?array
    {
        return match ($this) {
            self::Theme, self::NavbarTheme, self::SidebarTheme => [null, 'light', 'dark'],
            self::NavbarExpand, self::SidebarExpand => ['sm', 'md', 'lg', 'xl'],
            self::SidebarPosition => ['start', 'end'],
            default => null,
        };
    }

    /**
     * The type string used in `{% types %}` declarations and in the documentation.
     */
    public function type(): string
    {
        $allowed = $this->allowedValues();

        if ($allowed !== null) {
            return implode('|', array_map(
                static fn (?string $value): string => $value === null ? 'null' : "'" . $value . "'",
                $allowed,
            ));
        }

        return is_bool($this->default()) ? 'boolean' : 'string|null';
    }

    public function description(): string
    {
        return match ($this) {
            self::Theme => 'Forces the colour scheme instead of following the user preference',
            self::Fluid => 'Use the full width of the viewport',
            self::Boxed => 'Constrain the page to a boxed width',
            self::Container => 'Overrides the container class outright, e.g. container-md',
            self::Navbar => 'Render the top navigation bar',
            self::NavbarTheme => 'Colour scheme of the top navigation bar',
            self::NavbarSticky => 'Keep the top navigation bar visible while scrolling',
            self::NavbarOverlap => 'Let the page body overlap the top navigation bar',
            self::NavbarExpand => 'Breakpoint at which the top navigation bar expands',
            self::Sidebar => 'Render the sidebar',
            self::SidebarTheme => 'Colour scheme of the sidebar',
            self::SidebarPosition => 'Side the sidebar sits on',
            self::SidebarTransparent => 'Remove the sidebar background',
            self::SidebarExpand => 'Breakpoint at which the sidebar expands',
            self::PageHeader => 'Render the page header',
            self::Footer => 'Render the page footer',
            self::Centered => 'Centre the content in a narrow column (clean layout only)',
        };
    }

    /**
     * Whether the option can be set application-wide under `platform.ui.layout`.
     *
     * `container` and `centered` are per-page decisions — a global container override or a
     * globally centred page is never what an application wants — so they are template-only.
     */
    public function isConfigurable(): bool
    {
        return match ($this) {
            self::Container, self::Centered => false,
            default => true,
        };
    }

    /**
     * @return list<self>
     */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $option): bool => $option->isConfigurable()));
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The full set of options at their default values.
     *
     * @return array<string, bool|string|null>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::cases() as $option) {
            $defaults[$option->value] = $option->default();
        }

        return $defaults;
    }

    public function accepts(mixed $value): bool
    {
        $allowed = $this->allowedValues();

        if ($allowed !== null) {
            return in_array($value, $allowed, true);
        }

        return is_bool($this->default()) ? is_bool($value) : ($value === null || is_string($value));
    }
}
