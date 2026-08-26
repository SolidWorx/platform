# Layouts

The UI bundle ships three page layouts built on [Tabler](https://tabler.io/) (Bootstrap 5). They all
extend one base template that owns the HTML document, so assets, meta tags, flash messages and the
page header behave identically no matter which layout a page uses.

| Layout | Twig global | Navigation | Use it for |
|--------|-------------|------------|------------|
| App | `ui_layout_app` | Sidebar + top navbar | The signed-in application: dashboards, admin, settings |
| Condensed | `ui_layout_condensed` | Top navbar only | Pages that hide the app navigation but keep top-level links: onboarding, checkout |
| Clean | `ui_layout_clean` | None | Login, two-factor, password reset, error pages |

Always extend the **global**, never the file path. An application can point any global at its own
template through [`ui.templates.layouts`](../configuration/index.md#ui-ui), and every page follows
automatically.

```twig
{% extends ui_layout_app %}
```

---

## Getting a page on screen

A page needs a title and some content. Everything else — document `<title>`, breadcrumb-height
header, flash messages, footer, assets — comes for free.

```twig
{% extends ui_layout_app %}

{% block page_pretitle %}{{ 'Billing'|trans }}{% endblock %}
{% block page_title %}{{ 'Invoices'|trans }}{% endblock %}

{% block page_title_actions %}
    <a href="{{ path('app_invoice_add') }}" class="btn btn-primary">
        {{ ux_icon('tabler:plus') }}
        {{ 'New invoice'|trans }}
    </a>
{% endblock %}

{% block content %}
    <twig:Ui:Card title="{{ 'Outstanding'|trans }}">
        …
    </twig:Ui:Card>
{% endblock %}
```

`page_title` does double duty: it fills `<h2 class="page-title">` **and** the document title, which
is rendered as `Invoices · Acme Platform`. The page header is skipped entirely when neither a title,
a pre-title nor actions are defined, so a bare `content` block still renders a valid page.

---

## Layout options

The recommended way to set them is `ui_layout()`:

```twig
{% extends ui_layout_app %}

{% set layout = ui_layout(navbar_theme: 'dark', navbar_sticky: true, fluid: true) %}
```

`ui_layout()` is a Twig function backed by a real PHP method, so the option names are its parameter
names. That buys three things a bare hash cannot:

- **Your IDE can complete and check the argument list**, because it resolves the function to a PHP
  signature. See [Editor support](#editor-support) for what each editor does with it.
- **An option that does not exist is a compile-time error**, and Twig prints the whole valid list:

  ```
  Unknown argument "navbarstick" for function "ui_layout(theme, fluid, boxed, container, navbar,
  navbar_theme, navbar_sticky, navbar_overlap, navbar_expand, sidebar, sidebar_theme,
  sidebar_position, sidebar_transparent, sidebar_expand, page_header, footer, centered)"
  ```

- **A value outside the allowed set is rejected** — `navbar_expand: 'xxl'` fails with
  `Expected "sm", "md", "lg", "xl"`.

A plain hash still works and is validated identically, just without the IDE support:

```twig
{% set layout = {navbar_theme: 'dark', navbar_sticky: true, fluid: true} %}
```

Either way a typo is loud rather than silent — `{navbar_stick: true}` fails with
`Unknown layout option "navbar_stick". Did you mean "navbar_sticky"?`.

Options are resolved once, in the base layout, from three sources in increasing order of precedence:

1. the built-in defaults
2. `ui.layout.*` in `platform.yaml` — your application-wide house style
3. the `layout` variable the template sets

| Option | Type | Default | Effect |
|--------|------|---------|--------|
| `theme` | `light` / `dark` / `null` | `null` | Forces the colour scheme on `<html>` instead of following the user's preference |
| `fluid` | bool | `false` | Full-width page (`body.layout-fluid`, `container-fluid`) |
| `boxed` | bool | `false` | Boxed page width (`body.layout-boxed`) |
| `container` | string / `null` | `null` | Overrides the container class outright, e.g. `container-md` |
| `navbar` | bool | `true` | Renders the top navigation bar |
| `navbar_theme` | `light` / `dark` / `null` | `null` | Colour scheme of the top navbar |
| `navbar_sticky` | bool | `false` | Keeps the top navbar visible while scrolling (`sticky-top`) |
| `navbar_overlap` | bool | `false` | Lets the page body overlap the navbar (`navbar-overlap`) |
| `navbar_expand` | `sm`/`md`/`lg`/`xl` | `md` | Breakpoint at which the navbar expands |
| `sidebar` | bool | `true` | Renders the sidebar |
| `sidebar_theme` | `light` / `dark` / `null` | `dark` | Colour scheme of the sidebar |
| `sidebar_position` | `start` / `end` | `start` | Side the sidebar sits on (`navbar-end`) |
| `sidebar_transparent` | bool | `false` | Removes the sidebar background (`navbar-transparent`) |
| `sidebar_expand` | `sm`/`md`/`lg`/`xl` | `lg` | Breakpoint at which the sidebar expands |
| `page_header` | bool | `true` | Renders the page header |
| `footer` | bool | `true` | Renders the page footer |
| `centered` | bool | `false` | Centres the content in a narrow column — `clean` layout only |

That table is generated from
[`LayoutOption`](../../src/Bundle/Ui/Layout/LayoutOption.php), which is the single source of truth:
the `platform.ui.layout` configuration tree, the `ui_layout()` signature and the runtime defaults
all come from it. Adding an option is one enum case.

`container` and `centered` are the only two that are template-only — a global container override or
a globally centred page is never what an application wants, so they are absent from
`platform.ui.layout`.

Two more variables follow the same convention:

```twig
{% set body_class = 'dashboard' %}
{% set brand_url = path('app_home') %}
```

### Reproducing the Tabler layout demos

| Tabler demo | Configuration |
|-------------|---------------|
| [Combo](https://preview.tabler.io/layout-combo.html) | `ui_layout_app` (the default) |
| [Vertical](https://preview.tabler.io/layout-vertical.html) | `ui_layout_app` with `{navbar: false}` |
| [Fluid vertical](https://preview.tabler.io/layout-fluid-vertical.html) | `ui_layout_app` with `{navbar: false, fluid: true}` |
| [Vertical right](https://preview.tabler.io/layout-vertical-right.html) | `ui_layout_app` with `{sidebar_position: 'end'}` |
| [Vertical transparent](https://preview.tabler.io/layout-vertical-transparent.html) | `ui_layout_app` with `{sidebar_transparent: true, sidebar_theme: null}` |
| [Condensed](https://preview.tabler.io/layout-condensed.html) | `ui_layout_condensed` |
| [Navbar dark](https://preview.tabler.io/layout-navbar-dark.html) | `ui_layout_condensed` with `{navbar_theme: 'dark'}` |
| [Navbar sticky](https://preview.tabler.io/layout-navbar-sticky.html) | `ui_layout_condensed` with `{navbar_sticky: true}` |
| [Navbar overlap](https://preview.tabler.io/layout-navbar-overlap.html) | `ui_layout_condensed` with `{navbar_overlap: true, navbar_theme: 'dark'}` |
| [Boxed](https://preview.tabler.io/layout-boxed.html) | any layout with `{boxed: true}` |
| [Sign in](https://preview.tabler.io/sign-in.html) | `ui_layout_clean` with `{centered: true}` |

---

## Navigation

Links are not hard-coded into the layouts — they come from KnpMenu menus, which the layouts look up
by name. If no builder registers a menu, that part of the navigation is simply not rendered.

| Menu name | Rendered in |
|-----------|-------------|
| `sidebar` | The vertical sidebar of the `app` layout |
| `navbar` | The top navigation bar of the `app` and `condensed` layouts |

```php
use Knp\Menu\ItemInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\Options;

final class AppMenu
{
    #[MenuBuilder(name: 'sidebar')]
    public function build(ItemInterface $menu): void
    {
        $menu->addChild('Dashboard', Options::create()
            ->route('app_dashboard')
            ->icon('home')
            ->build());

        $settings = $menu->addChild('Settings', Options::create()
            ->icon('settings')
            ->role('ROLE_ADMIN')
            ->build());

        $settings->addChild('Team', Options::create()->route('app_team')->build());
    }
}
```

Items without a URI render as dropdown toggles; items with children render a Tabler dropdown. Use
`->role()` to hide entries the current user may not see.

---

## Blocks

Every structural piece of the layouts is a Twig block, so a page can replace one part without
rebuilding the rest. Blocks are pulled in with `{% use %}`, which means they are overridable from
any page template — not just from a layout.

| Block | Available in | Purpose |
|-------|--------------|---------|
| `content` | all | The page body |
| `page_pretitle`, `page_title`, `page_title_actions` | all | The page header |
| `page_header` | all | The entire page header |
| `flashes` | all | Flash message rendering |
| `footer`, `footer_links`, `footer_copyright` | all | The page footer |
| `brand`, `brand_content` | all | The brand / logo |
| `user_menu`, `user_menu_avatar`, `user_menu_name`, `user_menu_role`, `user_menu_items` | `app`, `condensed` | The authenticated user dropdown |
| `navbar`, `navbar_brand`, `navbar_menu`, `navbar_actions` | `app`, `condensed` | The top navigation bar |
| `sidebar`, `sidebar_brand`, `sidebar_menu`, `sidebar_footer` | `app` | The sidebar |
| `clean_brand` | `clean` | The brand above a centred card |
| `head`, `head_title`, `head_meta`, `favicon`, `stylesheets`, `javascripts` | all | The HTML document |
| `html_attributes`, `body_attributes` | all | Extra attributes on `<html>` / `<body>` |

### Replacing the text brand with a logo

```twig
{% block brand_content %}
    <img src="{{ asset('images/logo.svg') }}" width="110" height="32" alt="" class="navbar-brand-image" />
{% endblock %}
```

Do this once in your own base template — set `ui.templates.base` to a template that extends
`@Ui/Layout/base.html.twig` and overrides `brand_content` — and every layout picks it up.

### Adding entries to the user dropdown

```twig
{% block user_menu_items %}
    <a href="{{ path('app_profile') }}" class="dropdown-item">{{ 'Profile'|trans }}</a>
    <div class="dropdown-divider"></div>
    {{ parent() }}
{% endblock %}
```

### Adding actions to the top navbar

```twig
{% block navbar_actions %}
    <div class="nav-item">
        <a href="{{ path('app_search') }}" class="nav-link px-0" aria-label="{{ 'Search'|trans }}">
            {{ ux_icon('tabler:search') }}
        </a>
    </div>
{% endblock %}
```

---

## Editor support

**The short answer on autocomplete:** nothing completes keys inside a Twig hash literal. There is no
mechanism in Twig, in `{% types %}` or in PhpStorm's Symfony plugin that can look at
`{% set layout = { … } %}` and offer you the option names. That is exactly why `ui_layout()` exists —
it turns the options into a PHP signature, which every tool already knows how to read.

What you get, in order of how much your editor has to support:

**Always, from Twig itself.** `ui_layout()` is validated when the template compiles. Pass an option
that does not exist and Twig refuses to compile the template and prints every valid name; pass a bad
value and the render fails with the accepted set. This needs no plugin, no LSP and no configuration
— it works in CI too.

**In PhpStorm, via the Symfony plugin.** Twig functions resolve to the PHP callable behind them, so
<kbd>Ctrl</kbd>/<kbd>⌘</kbd>-click on `ui_layout` jumps straight to
[`LayoutRuntime::layout()`](../../src/Bundle/Ui/Twig/Runtime/LayoutRuntime.php), where the whole
option list is one signature with types and defaults. Whether the plugin also completes the named
arguments as you type depends on its version — the jump-to-definition and the compile-time check
work regardless.

**In editors that understand `{% types %}`.** Every layout and partial declares the variables it
works with:

```twig
{% types {
    ## The resolved layout options, from `ui_layout_resolve()` in the base layout.
    layout: 'map',
    ## The Bootstrap container class the page body uses, e.g. `container-xl`.
    container_class: 'string',
} %}
```

Symfony Language Tools reads these and shows the declared type, whether the variable is required or
optional, and the attached description on hover and completion. Note that Twig deliberately keeps
type declarations local to the template they appear in — they are not propagated to templates that
extend or include it — so these document the layout you are editing, not a page extending it.

**Documentation comments.** Every block carries a `{## … ##}` comment explaining what it is for:

```twig
{## The navigation links, rendered from the KnpMenu menu named `navbar`. ##}
{% block navbar_menu %}
```

[Twig 3.29](https://symfony.com/blog/new-in-twig-3-29-documentation-comments) attaches these to the
parsed node so an IDE can show them while you pick a block to override. On older Twig they are
parsed as ordinary comments, so the templates render identically either way.

The `{% types %}` tag needs Twig 3.13, which the platform requires (`twig/twig ^3.13`). Documentation
comments need 3.29 to become machine-readable, but degrade to plain comments before that, so there
is no version floor for them.

---

## Writing your own layouts

If you replace a layout or build your own, two conventions keep it as discoverable as the shipped
ones:

- **Declare what the template needs with `{% types %}`**, using `?` for optional variables, and put
  an inline `##` comment above each declaration. Inline documentation comments consume the rest of
  the line, so the variable they describe has to start on the next one.
- **Put a `{## … ##}` comment above every block** you expect anyone to override, saying what it is
  for rather than what it contains.

Both are inert on Twig 3.28 and below, so adopting them costs nothing.

## Flash messages

Every layout renders flash messages immediately above `content`, as dismissible Tabler alerts. The
flash label picks the styling:

| Label | Alert |
|-------|-------|
| `error`, `danger` | `alert-danger` |
| `warning` | `alert-warning` |
| `success` | `alert-success` |
| `notice`, `info`, anything else | `alert-info` |

```php
$request->getSession()->getFlashBag()->add('success', 'Invoice sent.');
```

---

## Replacing a layout entirely

Point the config at your own template and keep the platform's base:

```yaml
ui:
  templates:
    layouts:
      app: '@App/layout/app.html.twig'
```

```twig
{# templates/layout/app.html.twig #}
{% extends ui_base_template %}

{% use '@Ui/Layout/partials/_sidebar.html.twig' %}

{% block body %}
    …your own structure, reusing the platform's sidebar blocks…
{% endblock %}
```

Replacing `ui.templates.base` instead swaps the HTML document for *all* layouts — use that for
extra `<head>` tags, analytics snippets, or a different asset entry.

---

## Next steps

- [Theming & customization](./customization.md) — SCSS variables, brand colours
- [Configuration reference](../configuration/index.md#ui-ui) — the `ui` section of `platform.yaml`
