# Multi-Tenancy

The PlatformBundle ships an opt-in multi-tenancy layer that scopes data per tenant. It is a
**security feature**: when enabled, every query against a tenant-aware entity is automatically
filtered to the tenant in scope, writes cannot cross tenant boundaries, and an authenticated user
cannot enter a tenant they are not a member of.

Multi-tenancy is **disabled by default**. When disabled, all tenancy services are removed from the
container and no tenant tables are mapped.

## Enabling

```yaml
# platform.yaml
platform:
    multi_tenancy:
        enabled: true
        session_key: _tenant_id          # session key holding the selected tenant id
        route_param: tenant              # route parameter for the route resolver
        validate_user_access: true       # deny entering a tenant the user is not a member of
        resolvers:
            domain: true                 # resolve by custom request host (highest priority)
            session: true                # resolve from the session (post-login default)
            route: false                 # resolve from a route parameter
        write_guard:
            check_user_access: false     # also verify the user is a member on write
```

Enabling multi-tenancy maps two entities — `Tenant` (`platform_tenant`) and `UserTenant`
(`platform_user_tenant`) — and registers the `tenant` Doctrine filter (disabled until a tenant is in
scope). Generate/refresh your schema afterwards (e.g. a Doctrine migration).

## Making an entity tenant-aware

Implement `TenantAwareInterface` and use `TenantAwareTrait`:

```php
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareTrait;

#[ORM\Entity]
class Invoice implements TenantAwareInterface
{
    use TenantAwareTrait; // adds the nullable `tenant_id` ULID column + accessors
}
```

The trait adds a nullable `tenant_id` column. On insert, it is populated automatically from the
tenant in scope; you never set it by hand.

### Indexing

A standalone index on `tenant_id` is added automatically to every tenant-aware entity, and
`tenant_id` is forced to be the **leading column** of any composite index or unique constraint it
appears in (optimal for tenant-scoped lookups). Design composite indexes knowing tenant comes
first — e.g. an index on `['status', 'tenantId']` is automatically reordered to lead with
`tenant_id`. Reordering is safe: column order is a read optimisation and does not change uniqueness
semantics.

Note that unique constraints become **per-tenant** automatically (the constraint is over the column
set including `tenant_id`), which is usually what you want.

## How a tenant is established

On each main request, the `TenantRequestListener` walks a priority-ordered chain of resolvers and
the first non-null result wins:

| Resolver | Priority | Source |
|----------|----------|--------|
| `DomainTenantResolver` | highest | the request host, matched against `Tenant::$domain` |
| `SessionTenantResolver` | medium | the session key (`_tenant_id` by default) |
| `RouteTenantResolver` | lowest | a route parameter (`tenant` by default), disabled by default |

There is intentionally **no header/API-key resolver** — letting a caller assert any tenant via a
request header would be a privilege-escalation vector.

### Custom resolvers

Implement `TenantResolverInterface` and tag the service with `platform.tenant_resolver`, giving it a
priority:

```php
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\TenantResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('platform.tenant_resolver', ['priority' => 25])]
final class MyResolver implements TenantResolverInterface
{
    public function resolve(\Symfony\Component\HttpFoundation\Request $request): ?\Symfony\Component\Uid\Ulid
    {
        // ...
    }
}
```

## Access validation on switch

Whenever a tenant is applied, the `TenantAccessValidationListener` (a high-priority listener on
`TenantSwitchedEvent`) verifies — when an authenticated user is present — that the user is a member
of the tenant (`UserTenant` row). If not, it throws `TenantAccessDeniedException` (a 403 in HTTP)
**before** the tenant is committed to the context or the filter is enabled.

This is the single, uniform membership check for every resolver, so resolvers never re-validate. It
is skipped when there is no authenticated user (anonymous request on a custom domain, console
command, message worker), where the domain or system is the trust anchor. Disable it with
`validate_user_access: false`.

## Switching tenants in code

Use the `TenantManager` facade rather than touching the context and filter directly:

```php
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;

public function __construct(private readonly TenantManager $tenantManager) {}

$this->tenantManager->switchTo($tenant);   // apply a tenant (validated)
$this->tenantManager->clear();             // leave all tenants
```

### Cross-tenant operations

For deliberate cross-tenant work (reporting, batch jobs), bypass the filter or iterate per tenant:

```php
// Run a query across all tenants, restoring the filter afterwards.
$all = $this->tenantManager->runWithoutFilter(fn () => $repository->findAll());

// Per-tenant iteration (e.g. an invoicing-reminder command).
foreach ($tenants as $tenant) {
    $this->tenantManager->runAs($tenant, function () use ($repository): void {
        foreach ($repository->findDueReminders() as $reminder) {
            // ... scoped to $tenant
        }
    });
}
```

Inside a repository, `TenantFilterAwareTrait::withoutTenantFilter()` provides the same bypass.

## The tenant-selection page

A ready-made page lets an authenticated user pick a tenant. It is served at `/tenant/select`
(route `solidworx_platform_tenant_select`): a GET lists the user's tenants, a POST stores the choice
in the session (picked up by the `SessionTenantResolver`). Access is guarded by the `TENANT_ACCESS`
voter.

## The write guard

The `TenantWriteGuardListener` (on `onFlush`) rejects any insert or update of a tenant-aware entity
whose tenant differs from the one in scope, throwing `CrossTenantOperationException`. It stands down
when no tenant is in scope (deliberate cross-tenant batch). With
`write_guard.check_user_access: true`, it additionally verifies the current user is a member of the
tenant being written to.

## The `TENANT_ACCESS` voter

Authorize access to a specific tenant with the voter:

```php
$this->denyAccessUnlessGranted(\SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter::TENANT_ACCESS, $tenant);
```

## Messenger integration

When `symfony/messenger` is installed, register the `TenantMiddleware` on your bus to propagate the
tenant across the message bus:

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - SolidWorx\Platform\PlatformBundle\Messenger\TenantMiddleware
```

On dispatch the current tenant is recorded on a `TenantStamp`; on handling in a worker it is restored
for the duration of the handler and cleared afterwards — so messages (including Scheduler-dispatched
ones) are always processed in their originating tenant. A message may also carry the tenant in its
own payload by implementing `TenantAwareMessageInterface` (use `TenantAwareMessageTrait`).

## Testing

`SolidWorx\Platform\Test\Traits\InteractsWithTenantsTrait` provides helpers (`createTenantContext()`,
`createTenant()`, `setCurrentTenant()`, `runAsTenant()`) for driving the tenant in scope from tests.

## Caveats

- **Native / raw DBAL queries are not filtered.** ORM SQL filters only apply to DQL/QueryBuilder.
  Scope native queries manually.
- **Unique constraints become per-tenant** on tenant-aware entities (usually desirable).
- **Second-level cache:** do not put tenant-aware entities in Doctrine's 2nd-level cache without a
  tenant-keyed region, or rows could leak across tenants.
