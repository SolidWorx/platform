# Customization

## Configuration reference

`datagrid:` is a top-level section, a sibling of `platform:` — not nested
under it (the same way `ui:` is). All eight keys are optional; every one has a
default.

```yaml
datagrid:
  # Register the data grid bundle. false removes it and pentiminax/ux-datatables
  # entirely -- no compiler pass, no Ajax routes, nothing to configure.
  # Default: true
  enabled: true

  # Rows shown per page.
  # Default: 25
  page_length: 25

  # Page size options offered to the user in the length menu.
  # Default: [10, 25, 50, 100]
  length_menu: [10, 25, 50, 100]

  # Collapse overflowing columns into an expandable child row on narrow screens.
  # Default: true
  responsive: true

  # Show per-column ordering and search controls in the header.
  # Default: true
  column_control: true

  # CSS classes applied to every rendered <table> element.
  # Default: 'table table-vcenter card-table'
  table_class: 'table table-vcenter card-table'

  security:
    # Symfony security attribute (role or expression) required to reach any
    # of the /datatables/ajax/* routes. See docs/datagrid/security.md.
    # Default: 'IS_AUTHENTICATED_FULLY'
    ajax_access: 'IS_AUTHENTICATED_FULLY'

  export:
    # Add server-side export buttons to every grid.
    # Default: true
    enabled: true

    # Export formats offered. One or both of 'csv', 'xlsx'.
    # Default: ['csv', 'xlsx']
    formats: ['csv', 'xlsx']
```

`page_length` must be a positive integer, `security.ajax_access` cannot be
empty, and `export.formats` only accepts `csv` and `xlsx` — an unknown format
is rejected when the container compiles.

## The PHP fluent builder

`DataGridConfigBuilder` mirrors a subset of the above for `platform.php`
config (or anywhere else you assemble the array in PHP). It only exposes
fluent methods for the keys most apps override:

| Method | Sets |
|--------|------|
| `disabled()` | `enabled: false` — there is no `enabled(bool)` method; the builder only ever turns the bundle off, never explicitly on (the default is already on) |
| `pageLength(int)` | `page_length` |
| `lengthMenu(list<int>)` | `length_menu` |
| `tableClass(string)` | `table_class` |
| `ajaxAccess(string)` | `security.ajax_access` |
| `exportFormats(list<'csv'\|'xlsx'>)` | `export.formats` |

`responsive` and `column_control` are not covered by the builder and must be
set through the plain array form (or YAML) if you need to change them from
their defaults.

```php
use SolidWorx\Platform\DataGridBundle\Config\Builder\DataGridConfigBuilder;

return [
    'datagrid' => DataGridConfigBuilder::create()
        ->pageLength(50)
        ->ajaxAccess('ROLE_ADMIN')
        ->build(),
];
```

To disable the bundle entirely through the builder:

```php
DataGridConfigBuilder::create()->disabled()->build();
// ['enabled' => false]
```

`build()` only emits the keys you called a method for, so anything you don't
set keeps the platform default.

## Styling

The bs5 DataTables theme and its extensions (responsive, column control,
buttons) are imported and adjusted for Tabler in `assets/scss/_datagrid.scss`.
It intentionally contains **no rules for the length menu `<select>` or the
search `<input>`** — DataTables' own Bootstrap 5 integration already applies
Bootstrap's real `form-select`/`form-control` classes to both, sized inline
by the vendor CSS. A hand-written override here would only regress: it would
miss the dark-mode chevron Bootstrap's real `.form-select` rule provides, and
Tabler's layered focus box-shadow. Do not add rules for those two elements —
duplicating Bootstrap classes with custom CSS breaks dark mode.

What the stylesheet *does* override: it removes the double table border/
padding that results from Tabler's `.card-table` and DataTables' `dt-container`
wrapper both drawing chrome, restyles the sort-order chevrons to match
Tabler's muted style, and keeps the `ActionsColumn` button group
(`.btn-list`) from wrapping onto a second line.
