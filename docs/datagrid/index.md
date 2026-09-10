# Data Grids

A data grid renders a Doctrine entity as a paginated, sortable, searchable
table, styled to match Tabler. It wraps [`pentiminax/ux-datatables`](https://github.com/pentiminax/ux-datatables),
adding Doctrine-metadata column auto-detection, Tabler-styled icon and action
columns, and route-level security on top of it.

Declare the grid and render it:

```php
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;

#[AsDataTable(Client::class)]
final class ClientDataGrid extends AbstractDataGrid
{
}
```

```twig
<twig:Platform:DataGrid name="client" />
```

That's it — no controller, no route, no Ajax wiring. Columns are detected from
the `Client` entity's Doctrine mapping, the table fetches data server-side, and
the Ajax endpoint and table token the browser needs are generated
automatically. Grids are registered as regular container services, so
`ClientDataGrid` needs no constructor arguments and no manual registration.

- [Defining grids](./grids.md) — naming, the `#[AsDataTable]` attribute, the platform defaults, and the hooks that override them
- [Columns](./columns.md) — the Doctrine auto-detection rules, and the icon and action columns
- [Security](./security.md) — the route gate, per-grid authorization, and upstream's per-column/per-action permissions
- [Customization](./customization.md) — the full `datagrid:` configuration reference and where the Tabler styling lives

## Disabling

The grid bundle is registered by default. Turn it off entirely with:

```yaml
datagrid:
  enabled: false
```

When disabled, neither `SolidWorxPlatformDataGridBundle` nor
`Pentiminax\UX\DataTables\DataTablesBundle` is registered — there is no
compiler pass, no Ajax routes, and no config to validate.

## Out of scope

Mercure-based live updates (`#[AsDataTable(mercure: ...)]` upstream) are not
part of this integration. Nothing in the platform bundle configures a Mercure
hub for grids, and using the option is unsupported.
