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

Options are set with a plain `{% set %}` next to the `{% extends %}`:

```twig
{% extends ui_layout_app %}

{% set layout = {
    navbar_theme: 'dark',
    navbar_sticky: true,
    fluid: true,
} %}
```

They are resolved once, in the base layout, from three sources in increasing order of precedence:

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

Two more variables follow the same convention:

```twig
{% set body_class = 'dashboard' %}      {# extra classes on <body> #}
{% set brand_url = path('app_home') %}  {# where the brand links to #}
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
