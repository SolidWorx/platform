# Security

Every route `pentiminax/ux-datatables` registers is Ajax-only — there are no
controllers or templates of your own to secure. Three things gate access to
them: a bundle-wide route check, an optional per-grid check, and upstream's
own per-column/per-action permissions. Each is independent; none of them
substitutes for another.

## The bundle-wide route gate

`DataGridAccessSubscriber` guards all eight routes the bundle registers
(`ux_datatables_ajax_data`, `_templates`, `_edit`, `_delete`, `_edit_form`,
`_edit_form_submit`, `_detail`, `_export`). Every request to any of them must
be granted `datagrid.security.ajax_access` (default `IS_AUTHENTICATED_FULLY`,
see [Customization](./customization.md)) or the subscriber throws
`AccessDeniedException` before the request reaches a controller.

This is a `kernel.request` event subscriber, **not** a prepended
`security.access_control` rule. `access_control` is first-match-wins and
strictly ordered: inserting an entry into an application's existing rule list
would silently change the meaning of every rule that follows it. A subscriber
avoids that entirely, at the cost of not being visible in
`security.access_control`.

## The per-grid check

The bundle-wide gate answers "can this user reach the Ajax endpoints at
all?" — it says nothing about *which* grid a given request is for. The table
token embedded in the rendered page identifies which grid is being requested,
not who is asking, so `DataGridAccessSubscriber` also resolves the grid a
request's token points to and, when that grid is one of ours, checks
`getSecurityAttribute()` against it:

```php
#[Override]
public function getSecurityAttribute(): string
{
    return 'ROLE_ADMIN';
}
```

```php
use Symfony\Component\ExpressionLanguage\Expression;

#[Override]
public function getSecurityAttribute(): Expression
{
    return new Expression('is_granted("ROLE_ADMIN")');
}
```

Returning `null` (the default) means the bundle-wide gate is the only check
for that grid. There is no way to exempt a grid from the bundle-wide gate —
only to add to it.

**This check applies to all eight routes, not just the ones that read
data.** The token identifying a grid is carried differently depending on the
route:

| Routes | Token carried | Resolved via |
|--------|----------------|---------------|
| `ux_datatables_ajax_data`, `ux_datatables_ajax_export` | Query string, key `table` | `AjaxDataTableRegistry::get()` |
| `ux_datatables_ajax_templates` | Request body, key `table` | `AjaxDataTableRegistry::get()` |
| `ux_datatables_ajax_edit`, `_delete`, `_edit_form`, `_edit_form_submit`, `_detail` | Request body, key `dataTable` | `AjaxDataTableRegistry::resolveAction()` |

Every one of these is resolved and checked, because there is no safe way to
carve out a subset by token type: an authenticated user holding one grid's
token could otherwise use it to read or mutate a different, more sensitive
grid simply by knowing (or guessing) its route. An unresolvable token — a
garbage value, one that has expired, or one whose signature does not match —
is treated as "no grid to check," which does **not** bypass the check; the
request still reaches the controller, which independently re-derives and
rejects the same token.

### CSRF is a separate, narrower protection

Of the eight routes, only three validate a CSRF token, via upstream's own
`MutationTokenValidator` (an `X-CSRF-Token` header checked against the
`ux_datatables_mutation` token id): **`ux_datatables_ajax_edit`** (the inline
boolean toggle), **`ux_datatables_ajax_delete`**, and
**`ux_datatables_ajax_edit_form_submit`** (the full edit-modal submission).
The other five — `_data`, `_export`, `_templates`, `_edit_form` (opening the
modal), and `_detail` — carry no CSRF check at all; they are read-only, so
CSRF (which defends against a *mutation* triggered from another origin) does
not apply to them.

CSRF also proves a different thing than the per-grid check does: it proves
the request originated from your own page, never that the authenticated user
making it is allowed to read or mutate *this particular* grid. That is why
the per-grid check above applies uniformly across every route regardless of
its CSRF status — CSRF and authorization answer different questions, and
neither can stand in for the other.

## Upstream's per-column and per-action permissions

Independent of both checks above, upstream's own `AbstractColumn::setPermission()`
and `Action::setPermission()` restrict visibility of individual columns and
actions, evaluated per-request against the current security context (unlike
the `configure*()` hooks, which must stay pure and cannot read the security
token):

```php
use Symfony\Component\ExpressionLanguage\Expression;

TextColumn::new('internalNotes', 'Internal notes')
    ->setPermission('ROLE_ADMIN');

// or, with an Expression:
$action->setPermission(new Expression('is_granted("ROLE_ADMIN")'));
```

Both accept a role string or an `Expression`, exactly like
`getSecurityAttribute()`. Use these for "everyone can see this grid, but only
some users see this column/action" — `getSecurityAttribute()` is coarser: it
gates the whole grid.
