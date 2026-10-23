# Upgrade Guide

## 0.2 → 0.3

### Emailed links need a trusted host

**Breaking (production only):** password reset and email verification links are built from the
request's host. With nothing checking that host, anyone could trigger one of those emails to a
victim with a forged `Host` header, and the genuine link inside would point at their site. Outside
debug mode the platform now refuses to build such a link unless one of these is set:

- `framework.router.default_uri`: if your app already sets it, you're done. Emailed links start
  with it, whatever the request said.
- `platform.security.base_url` (new, for example `https://app.example.com`): the same, for the
  emailed links only. Takes precedence over `default_uri`. Both accept `%env()%`, resolved at
  runtime.
- `framework.trusted_hosts`: Symfony rejects requests for any other host. Use this with
  domain-based multi-tenancy, so each tenant's links keep their own domain. If you also set
  `default_uri`, set `platform.security.base_url: ''` so the links use the request's host.

Until one is set, verification emails are **not sent**: signing up still works, and a critical
message is logged. The resend button reports that the email could not be sent, and the reset
pages show an error naming both settings. Console commands and workers are unaffected; they use
`framework.router.default_uri`.

### Password reset

The platform now ships a "forgot password" flow, built on `symfonycasts/reset-password-bundle`.
It is **off by default**; nothing changes until you set
`platform.security.reset_password.enabled: true`. Then:

- The platform kernel registers `SymfonyCastsResetPasswordBundle`. If Composer's Flex recipe added
  it to `config/bundles.php`, **remove that line and `config/packages/reset_password.yaml`**; the
  platform registers and configures the bundle itself. (Left in place they are tolerated, but the
  bundle then loads even while the feature is off.)
- Create the new `reset_password_request` table with a migration.
- Make sure your emails have a sender, for example `framework.mailer.headers.from`.
- Set `framework.router.default_uri`, `platform.security.base_url` or `framework.trusted_hosts`; see
  [Emailed links](#emailed-links-need-a-trusted-host). Outside debug mode the reset pages refuse
  to run until one is set.
- The login page and the change-password page link to `/reset-password`, and
  `LoginExtension::defaultFormLoginConfig()` makes `^/reset-password` public ahead of your own
  access-control rules.

See [the password reset guide](./docs/security/reset-password.md).

`symfony/rate-limiter` moved from `require-dev` to `require`; the reset form is rate limited per IP.

**Changed markup:** the login page is now built on the new `Ui:AuthCard` component, and its
`social_login` block renders inside the card under the form. A template extending
`@Ui/Security/login.html.twig` that overrides `social_login` keeps working. The password rules list
on the change-password page is now the `Ui:PasswordRequirements` component; its
`password_requirements` block still exists.

### Email verification

The platform now owns email verification, built on `symfonycasts/verify-email-bundle`. The platform
kernel registers that bundle while the feature is enabled, so **remove
`SymfonyCasts\Bundle\VerifyEmail\SymfonyCastsVerifyEmailBundle` from your `bundles.php`** — a
bundle cannot be registered twice. It is on by default under `platform.security.email_verification`:

- New, unverified users get a signed link (valid **7 days**, `lifetime`) when they are saved.
- `GET /verify` (`_solidworx_platform_verify_email`) checks the link, marks the user verified and
  signs them in through the `form_login` of `firewall` (default `main`).
- `POST /verify/resend` (`_solidworx_platform_verify_email_resend`) sends a new link, CSRF-checked
  and limited to one a minute. Render the button with
  `@SolidWorxPlatform/EmailVerification/_resend_form.html.twig`.
- Unverified users see a banner, with that button, at the top of every page. Change its wording by
  pointing `banner_template` at a template extending
  `@SolidWorxPlatform/EmailVerification/banner.html.twig`. The profile page shows the button too.
- Gate actions with `EmailVerificationGateInterface::isGated()` (or the `is_email_verification_gated()`
  / `email_verification_message()` Twig functions), and refuse unverified users on a firewall with
  `Security\EmailVerification\VerifiedUserChecker`.

**Breaking:** `Model\UserInterface` now declares `getEmail()`, `isVerified()` and `setVerified()`.
Users extending `Model\User` already have them.

Set `enabled: false` to turn it all off: the bundle, the `/verify` routes and the services behind
them are not registered, and the gate never gates.

### Two-factor routes

The `2fa_login`, `2fa_login_check`, `/2fa/resend` and `/profile/two-factor` routes are now
registered by the platform kernel only when `platform.security.two_factor.enabled` is true. They
are no longer in `Resources/config/routing.php` or on controller attributes, so nothing changes in
your routing config; with 2FA disabled the routes simply no longer exist.

### User profile section

Every signed-in user now gets a profile section — `/profile`, `/profile/edit`, `/profile/password`
and, when 2FA is enabled, `/profile/two-factor` — with a navigation column listing its pages, and
a **Profile** entry leading the user dropdown. See
[the profile guide](./docs/security/profile.md).

Nothing is required to upgrade, but these changed shape:

- **Two-factor authentication moved out of the user dropdown.** It is now an entry on the new
  `profile_menu`, so it sits with the other account pages instead of behind the avatar. The route
  and the path are unchanged, so existing links keep working. If you linked to it from a menu
  builder of your own, nothing breaks — but the platform no longer puts it in `user_menu`.
- **`UserMenu::PRIORITY_ACCOUNT` no longer has a platform entry at it.** The constant stays, as the
  anchor for registering an account-level entry in the dropdown. Anything you registered relative to
  it still lands where it did.
- **Profile pages extend `profile_layout` rather than `ui_layout_app`.** A template of your own that
  extends a shipped profile page picks the navigation up automatically. One that *replaced* a page
  outright keeps working as it is, and can opt into the section by extending `profile_layout`.
- **`show.html.twig` no longer exports the `security_item()` macro.** Security rows are now
  `<twig:Ui:SettingRow>`, the shared component the two-factor page uses as well, so a setting reads
  the same wherever it appears. Replace `{{ profile.security_item(…) }}` calls with the component —
  see [the profile guide](./docs/security/profile.md#overriding-blocks). The `detail()` macro is
  unchanged.
- **`show.html.twig` no longer defines `page_title_actions`.** The "Update profile" button lives in
  the details card footer. Override the block yourself if you want a header action back.
- **Every Symfony password field now renders a show/hide toggle**, from the form theme's
  `password_widget`. No markup changes are needed; if you had built your own toggle around a
  `PasswordType`, remove it or you will get two.

- **`SolidWorx\Platform\PlatformBundle\Model\UserInterface` gained `setPassword(string): static`.**
  It is what lets the platform rotate a password on the user's behalf. Classes extending
  `Model\User` already have it; a class implementing the interface directly has to add it.
- **`Model\User::setMobile()` now accepts `null`.** The column has always been nullable, and an
  optional form field submits `null` when it is cleared. Widening a parameter type is
  backwards-compatible for callers; an override in your own user class has to widen with it.
- **`@SolidWorxPlatform/Form/theme.html.twig` now `{% use %}`s `bootstrap_5_layout.html.twig`.**
  The theme decorates blocks with `{{ parent() }}`, which Twig forbids in a template that neither
  extends nor uses another — so the theme could not previously be registered at all. Register it
  on its own now; it carries the Bootstrap layout with it, and there is no need to list
  `bootstrap_5_layout.html.twig` alongside it.

`platform.yaml` gained a `platform.profile` section (the form type, the four templates — including
`templates.layout`, the section's chrome — and the password rules). Every key is optional;
regenerate `platform-schema.json` with `php bin/console platform:generate-schema` to pick them up in
your editor.

The UI bundle also gained three shared Twig components — `Ui:SettingRow`, `Ui:SettingsNav` and
`Ui:PasswordField` — and `Ui:Card` gained `icon` and `iconColor` props. See
[UI Components](./docs/frontend/components.md). `Ui:Card` now wraps its title and subtitle in a
`<div>` so the subtitle sits under the title rather than beside it; a stylesheet targeting
`.card-header > .card-title` directly may need adjusting.

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
