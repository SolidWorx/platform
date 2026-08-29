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
        require_tenant: true             # always keep a tenant in scope for an authenticated user
        default_route: null              # where to land after selecting a tenant; null => "/"
        onboarding:
            enabled: true                # let a user with no tenants create their first
            form_type: SolidWorx\Platform\PlatformBundle\Form\Type\Tenant\TenantOnboardingType
        models:
            # The tenant + membership entities. Defaults are the platform's own entities;
            # override with your own to add fields (see "Customizing the tenant entity").
            tenant: SolidWorx\Platform\PlatformBundle\Entity\Tenant
            user_tenant: SolidWorx\Platform\PlatformBundle\Entity\UserTenant
        resolvers:
            domain: true                 # resolve by custom request host (highest priority)
            session: true                # resolve from the session (post-login default)
            route: false                 # resolve from a route parameter
        write_guard:
            check_user_access: false     # also verify the user is a member on write
```

Enabling multi-tenancy maps two entities by default — `Tenant` (`platform_tenant`) and `UserTenant`
(`platform_user_tenant`) — and registers the `tenant` Doctrine filter (disabled until a tenant is in
scope). Generate/refresh your schema afterwards (e.g. a Doctrine migration).

## Customizing the tenant entity

The shipped entities work out of the box. To add your own fields, extend the mapped base model and
register your class — mirroring how `platform.models.user` works:

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Model\Tenant as BaseTenant;

#[ORM\Entity]
#[ORM\Table(name: 'tenant')]
class Tenant extends BaseTenant
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $plan = null;

    // ... your accessors
}
```

```yaml
platform:
    multi_tenancy:
        models:
            tenant: App\Entity\Tenant
```

Your class must implement `TenantInterface` (the base model already does). The platform wires the
interface to your class with Doctrine `resolve_target_entities`, and the now-superseded default
entity is automatically demoted to a mapped superclass so it does not create a second table. The
membership entity (`UserTenant` / `UserTenantInterface`) is customizable the same way via
`models.user_tenant`.

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
| `DomainTenantResolver` | highest | the request host, matched against `Tenant::$domain` (locks the tenant — see [Custom domains](#custom-domains)) |
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

## Always having a tenant in scope

Resolvers establish a tenant when they can. The **scope guard** deals with the case where they
cannot: with `require_tenant: true` (the default), an authenticated user who reaches a page without
a tenant in scope is taken somewhere they can get one.

`TenantScopeGuardListener` runs on `kernel.controller` — late enough that the controller is known,
so the opt-out attribute can be read off it — and asks `TenantScopeResolver` what to do:

| The user belongs to | What happens |
|---|---|
| exactly one tenant | it is entered automatically and stored in the session; the request continues |
| more than one | redirected to `/tenant/select` |
| no tenant, onboarding enabled | redirected to `/tenant/onboarding` |
| no tenant, onboarding disabled | 403, rendering the "no workspace" page |

Auto-selection runs on *every* request that lacks a tenant, not only at login, so a session that
loses its tenant repairs itself on the next request. It goes through `TenantManager`, so the
membership check still applies — it is a convenience, never a way around validation.

The guard never redirects a non-navigational request. An XHR or a request negotiating JSON gets a
403 with a machine-readable body instead, because a 302 to an HTML page tells a `fetch()` caller
nothing.

Where the user was headed is remembered (GET requests only, as a path) and replayed once they have
a tenant; failing that they land on `default_route`, then `/`.

### Pages that work without a tenant

Anonymous requests are ignored outright, which covers login, 2FA, password reset and registration
without naming any of them. Everything else that must work outside a tenant — account settings,
billing, an admin console — carries `#[WithoutTenant]`:

```php
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;

#[WithoutTenant]                       // the whole controller
final class BillingController
{
    #[WithoutTenant]                   // or a single action
    public function invoices(): Response { /* ... */ }
}
```

Forgetting it on such a page produces a redirect loop — loud and immediate rather than silent.

## The tenant-selection page

A ready-made page lets an authenticated user pick a tenant. It is served at `/tenant/select`
(route `solidworx_platform_tenant_select`): a GET lists the user's tenants, a POST stores the choice
in the session (picked up by the `SessionTenantResolver`). Access is guarded by the `TENANT_ACCESS`
voter, and the page is forbidden outright while the tenant is locked to a custom domain.

Drop the switcher anywhere in your templates — it decides for itself whether it has anything to
show, so it needs no surrounding condition:

```twig
<twig:Platform:Tenant:Switcher />
```

It renders nothing when the user has one tenant or the tenant is domain-locked. It is already
included in the user menu (`_user_menu.html.twig`, block `user_menu_workspaces`).

## Onboarding

With `onboarding.enabled` (the default), a user with no tenants is sent to `/tenant/onboarding` to
create their first one. Turn it off for invite-only products, where tenants are provisioned out of
band; those users see the "no workspace" page instead.

The page is customisable at three levels, cheapest first.

**Add fields** by listening to `TenantOnboardingFormEvent`. Because the form is bound to your
configured tenant class, mapped fields land on your entity directly:

```php
use SolidWorx\Platform\PlatformBundle\Form\Event\TenantOnboardingFormEvent;

#[AsEventListener]
final class AddIndustryField
{
    public function __invoke(TenantOnboardingFormEvent $event): void
    {
        $event->getBuilder()->add('industry', ChoiceType::class, ['choices' => [/* ... */]]);
    }
}
```

**Replace the form** with `onboarding.form_type` when the shape of the page has to change (a
multi-step wizard, a different layout). The class must implement `FormTypeInterface`; this is
validated when the configuration is compiled.

**Change what happens on submit** by decorating or replacing the `TenantOnboarder` service. The
default persists the tenant, records the creator, creates the `UserTenant` membership, enters the
tenant and dispatches `TenantCreatedEvent`:

```php
#[AsDecorator(TenantOnboarder::class)]
final readonly class SeedingOnboarder implements TenantOnboarder
{
    public function __construct(private TenantOnboarder $inner) {}

    public function onboard(TenantInterface $tenant, UserInterface $user): void
    {
        $this->inner->onboard($tenant, $user);
        // ... seed default roles, sample data, a trial
    }
}
```

For seeding alone, listening to `TenantCreatedEvent` is simpler — it fires with the new tenant
already in scope, so anything tenant-aware you create is attributed to it automatically.

## Custom domains

A request arriving on a host matching `Tenant::$domain` belongs to that tenant, full stop.
`DomainTenantResolver` is a `LockingTenantResolverInterface`, so winning the chain also engages
`TenantLock` for the request:

- `TenantManager::switchTo()` and `clear()` throw `TenantLockedException`.
- `/tenant/select` returns 403.
- The switcher renders nothing.

`runAs()` and `runWithoutFilter()` are deliberately **exempt**. They are bounded, self-restoring
scopes for deliberate cross-tenant work, and a report or batch job must still run on a request that
happened to arrive via a custom domain. The lock exists to stop a *user* changing workspace, which
is what `switchTo()` and `clear()` express. Code that reaches past `TenantManager` to
`TenantContext` can still switch under a lock — one more reason to prefer the manager.

Membership is also checked at **login time** on a custom domain. `TenantDomainLoginListener` runs
inside the firewall on `CheckPassportEvent`, after credentials are verified, and fails
authentication for a user who is not a member of the domain's tenant. They stay on the login form
with an explanation and no session is created — as opposed to authenticating successfully and
meeting a 403 on the next request. It is active when `resolvers.domain` and `validate_user_access`
are both enabled.

Provisioning a domain (setting and verifying `Tenant::$domain`) is out of scope for the platform;
populate the column however suits your infrastructure.

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
