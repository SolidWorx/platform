# Upgrade Guide

## 0.2 → 0.3

### Tabler page layouts

The UI bundle now ships three layouts — `ui_layout_app` (sidebar + top navbar),
`ui_layout_condensed` (top navbar only) and `ui_layout_clean` (no navigation) — all extending the
existing `@Ui/Layout/base.html.twig`. See [the layouts guide](./docs/frontend/layouts.md).

Nothing is required to upgrade, but a few things changed shape:

- **`platform.yaml` gained `ui.templates.layouts` and `ui.layout`.** The first swaps a layout for
  your own template, the second sets application-wide layout defaults. Both are optional.
  Regenerate `platform-schema.json` with `php bin/console platform:generate-schema` to pick up the
  new keys in your editor.
- **`@Ui/Layout/security.html.twig` now extends `ui_layout_clean` in centred mode** and renders the
  brand above the card. Pages extending it keep working — the `content` block is unchanged. Remove
  the brand with `{% block clean_brand %}{% endblock %}`.
- **The document title now reads `<page title> · <app name>`.** Override the `head_title` block to
  restore the previous behaviour.
- **Flash messages render as `Ui:Alert` components** rather than hand-written `div.alert` markup,
  and every layout renders them — previously only the security layout did.
- **Two-factor templates fall back to `ui_layout_clean`** when
  `platform.security.two_factor.base_template` is not set, instead of failing to resolve a template.
- **`@SolidWorxPlatform/Menu/menu.html.twig` accepts two new render options**, `root_class` and
  `auto_close`, so the same menu renders in both the vertical sidebar and the horizontal navbar.
  The defaults are unchanged.
- **New `menu_exists(name)` Twig function**, so a template can render a menu only when a builder
  registered it.

### Layout options are validated, and `ui_layout()` is the preferred way to set them

Layout options now go through `SolidWorx\Platform\UiBundle\Layout\LayoutOption`, the single source
of truth behind the `platform.ui.layout` tree, the runtime defaults and the new Twig helpers.

- **New `ui_layout()` Twig function.** It takes the options as named arguments, so an option that
  does not exist is a compile-time error listing every valid name, and an IDE can resolve the whole
  list from the PHP signature:

  ```twig
  {% set layout = ui_layout(navbar_theme: 'dark', navbar_sticky: true) %}
  ```

  The plain hash form keeps working — both go through the same validation.

- **Unknown options and invalid values now throw** instead of being silently ignored. If you had a
  typo such as `{navbar_stick: true}`, it will now fail with
  `Unknown layout option "navbar_stick". Did you mean "navbar_sticky"?`.

- **New `ui_layout_resolve()` Twig function**, which merges the defaults, the application
  configuration and a template's own options. A custom base template should call it once:
  `{% set layout = ui_layout_resolve(layout|default({})) %}`.

- **The `ui_layout_defaults` Twig global has been removed.** It only existed so `base.html.twig`
  could merge the configuration by hand; `ui_layout_resolve()` does that now. Nothing else used it.

- **`twig/twig` now requires `^3.13`** (was `^3.12`) for the `{% types %}` tag the layouts use to
  declare their variables.

### Monolog is an optional dependency

`TenantLoggingProcessor` adds the current tenant to log records. It was registered unconditionally,
so applications without `monolog/monolog` installed failed to compile the container. It is now only
registered when Monolog is present; nothing changes for applications that already have it.

### Security config helpers migrated to Symfony 7.4 array-shape config (BC break)

Symfony 7.4 [deprecated the fluent PHP config-builder format](https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/DependencyInjection/CHANGELOG.md)
for semantic (bundle/extension) configuration in favour of returning a YAML-like
array-shape, e.g.:

```php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'security' => [
        'firewalls' => [
            'main' => ['pattern' => '^/', 'lazy' => true],
        ],
    ],
]);
```

The platform's reusable security helpers previously required live `Symfony\Config\*`
builder objects, so they could **not** be called from the new array-shape format —
apps migrating to array config were forced to hand-inline (copy/paste) the helpers'
output, which then silently drifted from the platform defaults on every upgrade.

The helpers are now **array-returning**, and the platform standardises on a single
config format — the fluent builders have been **removed** (see below):

- `LoginExtension::defaultFormLoginConfig(array $overrides = []): array` — returns the
  **complete** `security` payload (`['security' => …]`), with 2FA auto-detected.
- `TwoFactorExtension::securityConfig(): array` — the internal 2FA fragment.

#### New usage

The helper returns the whole `security` config, so the common case is one line:

```php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return App::config(LoginExtension::defaultFormLoginConfig());
```

App-specific settings go in `$overrides`, which is **deep-merged** onto the platform
defaults — associative arrays merge by key, scalars are overwritten, and sequential
lists (like `access_control`) are concatenated with the platform's values first. You
only write the keys you want to change:

```php
return App::config(LoginExtension::defaultFormLoginConfig([
    'password_hashers' => [App\Security\ApiUser::class => ['algorithm' => 'auto']],
    'providers' => ['api_users' => ['id' => App\Security\ApiUserProvider::class]],
    'firewalls' => [
        'main' => [
            'custom_authenticators' => [OAuthAuthenticator::class],
            'form_login' => ['default_target_path' => '_select_company'],
        ],
        'api' => ['pattern' => '^/api', 'stateless' => true],
    ],
    'access_control' => [
        ['path' => '^/admin', 'roles' => ['ROLE_ADMIN']],
    ],
]));
```

No more manual `array_replace_recursive()` or `...spread` — the helper does the merge,
and `access_control` ordering (platform rules before yours) is handled automatically.

#### Two-factor authentication is now auto-detected (the `enableTwoFactor` parameter is gone)

Previously you passed `defaultFormLoginConfig(enableTwoFactor: true)`. That parameter
has been **removed**. 2FA is now driven entirely by `platform.yaml`
(`platform.security.two_factor.enabled`): when it is on, the helper automatically folds
the `two_factor` firewall block and the 2FA access-control rules into its output.

This works via a small compile-time bridge: the platform kernel publishes the parsed
platform config to `SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState` at
boot, the helper reads the flag from it while `security.php` is evaluated, and a
compiler pass clears it at the end of the build. Requirement: your application kernel
must extend `SolidWorx\Platform\PlatformBundle\Kernel` (already a prerequisite). If it
does not, 2FA is treated as disabled.

#### BC break — the fluent config builders have been removed

The platform now supports a **single** config format. The fluent helpers that took
live `Symfony\Config\*` builder objects have been **removed** (not just deprecated):

- ~~`LoginExtension::configureDefaultFormLogin(SecurityConfig $config, bool $enableTwoFactor = false): FirewallConfig`~~
- ~~`TwoFactorExtension::configureSecurity(FirewallConfig $config, AccessControlConfig $accessControlConfig): void`~~

Migrate your `config/packages/security.php` to the array-shape format and call the
array helpers above. Concretely, replace:

```php
use Symfony\Config\SecurityConfig;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return static function (SecurityConfig $config): void {
    $main = LoginExtension::configureDefaultFormLogin($config, true);
    $main->customAuthenticators([OAuthAuthenticator::class]);
    $main->formLogin()->defaultTargetPath('_select_company');
};
```

with the array-shape form shown under **New usage** above. `TwoFactorExtension::enable()`
is unchanged.

#### Bug fix — the resend-code access-control rule is now registered and matches its route

When 2FA is enabled (via `platform.yaml`), the helper now emits **both** 2FA
access-control rules, with the more specific one first:

```php
['path' => '^/2fa/resend', 'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS']],
['path' => '^/2fa',        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS']],
```

Two things were wrong before. First, the fluent helper called `->path()` twice on the
*same* access-control entry; the second call overwrote the first, so only `^/2fa` ever
materialised. Second, the registered path (`^/2fa/resend-email`) did not match the
actual resend route, which is `/2fa/resend` (`ResendTwoFactorCode`).

Both are fixed: the rules are now distinct array entries, and the resend rule's path is
**derived from `ResendTwoFactorCode::PATH`** — the single source of truth shared with
the route attribute — so it always matches the real route and is ordered **before**
`^/2fa` so the broader prefix does not shadow it (access-control rules are matched
top-to-bottom, first match wins).
