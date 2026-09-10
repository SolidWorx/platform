# Defining Grids

Every grid is a plain class extending
`SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid`, which itself
extends upstream's `Pentiminax\UX\DataTables\Model\AbstractDataTable`.
Grids are registered as container services automatically — a grid class needs
no constructor arguments, because the platform's defaults are injected
through a setter (`setDataGridDefaults()`), not the constructor.

## The `#[AsDataTable]` attribute

`#[AsDataTable(Client::class)]` is the **only** attribute a grid needs — there
is no platform-specific `#[AsDataGrid]`. It tells the grid which Doctrine
entity to query and, when no columns are configured, which entity's metadata
to build columns from (see [Columns](./columns.md)).

```php
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;

#[AsDataTable(Client::class)]
final class ClientDataGrid extends AbstractDataGrid
{
}
```

Upstream resolves the attribute with `ReflectionClass::getAttributes(AsDataTable::class)`
— an **exact class match**, not `IS_INSTANCEOF`. A subclass of `AsDataTable`
would not be picked up, which is why the platform does not attempt to extend
or wrap the attribute itself; it uses the upstream attribute directly.

## Naming a grid

The name used in `<twig:Platform:DataGrid name="…" />` and in the Ajax token
is derived from the grid's short class name: strip a trailing `DataGrid` or
`Grid`, then convert to `snake_case`.

| Class | Name |
|-------|------|
| `ClientDataGrid` | `client` |
| `ClientsDataGrid` | `clients` |
| `App\Grid\InvoiceGrid` | `invoice` |

Override it with a `NAME` constant when the derived name is not what you
want, or to avoid a collision between two grids that would otherwise resolve
to the same name (the compiler pass throws a `LogicException` at build time
if two grids collide):

```php
#[AsDataTable(Client::class)]
final class ClientDataGrid extends AbstractDataGrid
{
    public const string NAME = 'overridden';
}
```

## The defaults `configureDataTable()` applies

`AbstractDataGrid::configureDataTable()` applies the platform's opinionated
defaults before your grid sees the table — server-side processing, the
Bootstrap 5 style framework (set explicitly, because upstream's automatic
detection sniffs stylesheet `<link>` hrefs, and Encore compiles everything
into one bundle so that detection would fail), the configured page length and
length menu, and the configured table CSS class. See
[Customization](./customization.md) for where each of these values comes
from and how to change them.

`configureExtensions()` similarly turns on the responsive, column-control and
export (CSV/XLSX) extensions according to configuration.

## Hooks you can override

Every `configure*()` method on `AbstractDataTable` remains overridable on
your grid subclass. Overriding one replaces the platform's behaviour for that
hook entirely (call `parent::configureXxx()` first if you want to keep the
platform defaults and add to them):

- `configureColumns(): iterable` — return your own list of columns instead of
  the ones auto-detected from Doctrine metadata. `AbstractDataGrid` only
  falls back to auto-detection when this returns an empty array.
- `configureFilters(Filters $filters): Filters` — declare filters rendered
  above the table (text, choice, ternary, date-range, or a custom `Filter`
  with a `query()` closure).
- `configureActions(Actions $actions): Actions` — declare upstream's own
  row-action system, as an alternative (or addition) to the platform's
  `ActionsColumn` — see [Columns](./columns.md) for when to reach for each.
- `configureExtensions(DataTableExtensions $extensions): DataTableExtensions`
  — add or remove DataTables extensions (buttons, responsive, column
  control, …).
- `customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder`
  — modify the base query (joins, an extra `WHERE`) before search and
  ordering are applied.
- `mapRow(mixed $row): array` — transform the row array sent to the browser.
  Call `parent::mapRow($row)` to keep the auto-mapped columns and add derived
  fields on top:

  ```php
  use Override;

  #[Override]
  protected function mapRow(mixed $row): array
  {
      $mapped = parent::mapRow($row);
      $mapped['fullName'] = $row->getFirstName() . ' ' . $row->getLastName();

      return $mapped;
  }
  ```

`configureFilters()`, `configureActions()`, `customizeQueryBuilder()` and
`mapRow()` are inherited unmodified from upstream's `AbstractDataTable` — the
platform does not add its own defaults for them, so their upstream
documentation applies as-is.

## Security per grid

`getSecurityAttribute()` is the one hook `AbstractDataGrid` adds that has no
upstream equivalent. See [Security](./security.md) for what it does and how
it combines with the bundle-wide route gate.

## Known constraint: rendering the same grid twice

Upstream's `DataTable::$id` is `readonly` and derived from the grid's short
class name, so it cannot vary per render call. Rendering the same grid twice
on one page (e.g. `<twig:Platform:DataGrid name="client" />` used twice)
still works, but the **second** table's element id gains a `-2` suffix — the
platform's renderer rewrites it after upstream produces the markup, since
reading the id back through `getDataTable()->getId()` would force the grid to
initialize before its defaults are available. If you render a grid more than
once, do not rely on its DOM id being the plain, undecorated one for anything
past the first occurrence.
