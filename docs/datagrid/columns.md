# Columns

When a grid's `configureColumns()` returns no columns — the default, unless
you override it — `AbstractDataGrid` builds a column list from the Doctrine
metadata of the entity declared on `#[AsDataTable]`. Upstream only
auto-detects columns from `#[Column]` attributes it defines itself, or from
API Platform metadata, so a plain Doctrine entity would otherwise render no
columns at all.

## Doctrine type mapping

Each mapped field becomes a column, chosen by its Doctrine DBAL type:

| Doctrine type(s) | Column |
|-------------------|--------|
| `boolean` | `BooleanColumn` |
| `integer`, `smallint`, `bigint`, `float` | `NumberColumn` |
| `decimal` | `MoneyColumn` |
| `date`, `date_immutable`, `datetime`, `datetime_immutable`, `datetimetz`, `datetimetz_immutable`, `time`, `time_immutable` | `DateColumn` |
| anything else (`string`, `text`, `json`, …) | `TextColumn` |

## Auto-detection rules

- **A field with no public reader is skipped entirely.** The default row
  mapper reads values through the property accessor, so a field with a
  private getter (or no getter at all) would otherwise map to `null` on
  every row. A field counts as readable when the entity exposes
  `get<Field>()`, `is<Field>()`, `has<Field>()`, or a public property of the
  same name.
- **A to-many association is skipped.** A collection cannot be ordered or
  searched in a single SQL statement, so `ManyToMany` and one-to-many fields
  never produce a column.
- **A to-one association is skipped unless its target implements `Stringable`.**
  Without a `__toString()` method there is nothing meaningful to render or
  search on.
- **The identifier field is not skipped, but it is marked non-searchable.**
  It is still shown and orderable — an id is useful to display and sort by —
  but excluded from free-text search, since a substring match against a
  numeric id rarely produces a meaningful result.

Column titles are humanized from the property name (e.g. `companyName` →
"Company Name") using upstream's `PropertyNameHumanizer`.

## Platform column types

Two column types exist only in this bundle, because upstream's own
equivalents don't render Tabler icons.

### `IconColumn`

Renders one icon per cell, chosen by the cell's value, through `ux_icon()` so
it matches the rest of the Tabler UI. Upstream's own `IconColumn` resolves
icon names from a Lucide-only enum in the browser instead, which does not
draw from the Tabler icon set — this is why the platform has its own.

```php
use SolidWorx\Platform\DataGridBundle\Column\IconColumn;

IconColumn::new('status', 'Status')
    ->icons(['active' => 'tabler:circle-check', 'suspended' => 'tabler:circle-x'])
    ->fallbackIcon('tabler:help-circle');
```

`icons()` maps a cell value to a `ux_icon()` icon name. `fallbackIcon()` is
used when the cell value matches no entry in the map (and may be `null`, in
which case no icon renders for an unmapped value).

### `ActionsColumn`

Renders row action buttons — edit, delete, and plain links — server-side,
also through `ux_icon()`.

```php
use SolidWorx\Platform\DataGridBundle\Column\ActionsColumn;

ActionsColumn::new()
    ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
    ->edit()
    ->delete(confirm: 'Delete this client?');
```

- `link(route, icon, label, routeParameter: 'id')` — a plain link, its URL
  generated per row by passing the row's identifier (by default the `id`
  field) as `routeParameter` to `path()`.
- `edit(icon: 'tabler:pencil', label: 'Edit')` — opens the inline edit modal
  for the row (the upstream Stimulus controller matches on
  `data-action-type="EDIT"`, which this column renders).
- `delete(icon: 'tabler:trash', label: 'Delete', confirm: null)` — deletes
  the row; pass `confirm` to render a `data-confirm` prompt before the delete
  fires.
- `identifiedBy('field')` — change which row key is used as the identifier
  passed to route generation and to the delete/edit Ajax calls (default
  `id`).

**`delete()` does not reproduce upstream's client-side button disabling.**
Upstream's own action renderer disables the delete button when no session is
available (`mutationsEnabled`); `ActionsColumn` renders its own markup and
does not check for this. The server still validates CSRF on the delete
route regardless (see [Security](./security.md)), so a delete attempted
without a valid session fails server-side with an error rather than being
disabled client-side ahead of time.

**Per-row Ajax actions are not supported by `ActionsColumn`.** Their CSRF
token is written into the row under the `__ux_datatables_actions` key, and
`RowProcessingPipeline` populates that key only *after* template columns
(which is what `ActionsColumn` and `IconColumn` both are) have already
rendered — so a `ActionsColumn`-rendered Ajax action would have no token to
send. If you need a per-row Ajax action, use upstream's own `ActionColumn`
(built through `configureActions()`, see [Defining grids](./grids.md))
instead. Its own action icons come from a `string` CSS class you supply or
from its Lucide-only `Icon` enum — neither renders a Tabler icon through
`ux_icon()` the way `ActionsColumn`'s do.
