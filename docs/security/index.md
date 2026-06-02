# Authentication & Security

SolidWorx Platform ships a batteries-included security layer so you can get a
production-grade login flow — form login, password hashing, remember-me, logout,
brute-force throttling, an auto-generated login page, and optional two-factor
authentication — wired up in a few lines instead of hand-writing a `security.yaml`
firewall from scratch.

One helper gives you the whole thing:

| Helper | Purpose |
|--------|---------|
| [`LoginExtension::defaultFormLoginConfig()`](#reference) | The **complete** default `security` config — `main` form-login firewall, password hasher, and (when `platform.yaml` enables it) the 2FA wiring. Returns `['security' => …]` ready for `App::config()`. |

> The platform standardises on Symfony 7.4's **array-shape** config format
> (`App::config([...])`). The helper returns the entire `security` payload, so the
> common case is a single line — see [Quick start](#quick-start). See
> [Configuration](../configuration/index.md) for the `platform.yaml` options
> referenced below.

---

## What it does

Calling `LoginExtension::defaultFormLoginConfig()` contributes a complete `main`
firewall plus its supporting config:

- **Form login** against the platform user provider, with CSRF protection.
- **A login page** — the platform auto-registers the `/login` route and renders a
  ready-made (and overridable) Twig template. You do **not** write a login controller
  or route.
- **Password hashing** for the platform `User` model using the `auto` algorithm
  (currently bcrypt/argon, chosen by Symfony).
- **Remember-me** cookies (7-day lifetime).
- **Logout** at `/logout`, clearing site data and invalidating the session.
- **Login throttling** — 5 attempts per 15 minutes, out of the box.
- **Two-factor authentication** (TOTP, email, backup codes, trusted devices),
  switched on **automatically** when `platform.security.two_factor.enabled` is true in
  `platform.yaml` — nothing extra in `security.php`. See
  [Two-Factor Authentication](./two-factor.md).

## When to use it

Use these helpers when you are building an application **on top of** SolidWorx
Platform and want the platform's conventional authentication behaviour with minimal
boilerplate. You still keep full control: every value is a plain array you can extend
or override (add your own authenticators, providers, access-control rules, change the
target path, etc. — see [Customising](#customising)).

If your app has bespoke authentication needs that share nothing with the platform
defaults, you can skip the helper and write your firewall directly — but most apps
want the defaults plus a few tweaks, which is exactly what this is for.

---

## Prerequisites

1. **Extend the platform kernel.** Your application kernel must extend
   `SolidWorx\Platform\PlatformBundle\Kernel`. This is what imports the
   auto-generated login routes (and the rest of the platform wiring).

   ```php
   // src/Kernel.php
   namespace App;

   use SolidWorx\Platform\PlatformBundle\Kernel as PlatformKernel;

   final class Kernel extends PlatformKernel
   {
   }
   ```

2. **Define a `User` entity.** Extend the platform's abstract user model. It already
   implements `UserInterface`, `PasswordAuthenticatedUserInterface` and the 2FA
   contract, so you only add what is specific to your app.

   ```php
   // src/Entity/User.php
   namespace App\Entity;

   use Doctrine\ORM\Mapping as ORM;
   use SolidWorx\Platform\PlatformBundle\Model\User as PlatformUser;

   #[ORM\Entity]
   #[ORM\Table(name: 'users')]
   class User extends PlatformUser
   {
       // app-specific fields, relations, etc.
   }
   ```

3. **Point the platform at your user class** (only if it differs from the default) in
   `platform.yaml`:

   ```yaml
   platform:
     models:
       user: App\Entity\User
   ```

   The platform automatically registers a Doctrine entity user provider named
   **`platform_user`** for this class — that's the provider the default firewall uses.

---

## Quick start

With the prerequisites in place, the entire default login flow is one line — the
helper returns the complete `security` payload, so you hand it straight to
`App::config()`:

```php
// config/packages/security.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return App::config(LoginExtension::defaultFormLoginConfig());
```

That's it — you now have:

- `GET /login` → the platform login page (see [the login page](#the-login-page)).
- A CSRF-protected login check that authenticates against `platform_user`.
- `GET /logout` → logout, redirecting to `/`.
- Remember-me and login throttling active on the `main` firewall.
- **Two-factor authentication wired in automatically** if
  `platform.security.two_factor.enabled` is `true` in your `platform.yaml` — you don't
  touch `security.php` for it. See [Two-Factor Authentication](./two-factor.md).

When you need app-specific settings, pass them as `$overrides` — they are deep-merged
onto the defaults (see [Customising](#customising)). You only write the keys you want
to change.

---

## The defaults in detail

`LoginExtension::defaultFormLoginConfig()` returns `['security' => [...]]` containing
`password_hashers`, `firewalls.main` and `access_control`. The `main` firewall it
produces is:

| Setting | Value | Notes |
|---------|-------|-------|
| `pattern` | `^/` | The firewall covers the whole app. |
| `entry_point` | `form_login` | Unauthenticated users are sent to the login form. |
| `provider` | `platform_user` | The auto-registered Doctrine entity provider. |
| `lazy` | `true` | The firewall is only initialised when needed. |
| `form_login.login_path` | `/login` | Where the login form is served. |
| `form_login.check_path` | `_login_check` | The route the form posts to. |
| `form_login.enable_csrf` | `true` | CSRF protection on the login form. |
| `form_login.always_use_default_target_path` | `true` | Always redirect to the default target after login. |
| `remember_me.lifetime` | `604800` (7 days) | Uses the `Time::WEEK` constant. |
| `remember_me.path` | `/` | Cookie path. |
| `logout.path` | `/logout` | The logout route. |
| `logout.target` | `/` | Redirect target after logout. |
| `logout.clear_site_data` | `cookies`, `storage`, `executionContexts` | Sent in the `Clear-Site-Data` header. |
| `logout.invalidate_session` | `true` | |
| `logout.enable_csrf` | `true` | CSRF-protected logout. |
| `login_throttling.max_attempts` | `5` | Per interval, per IP/username. |
| `login_throttling.interval` | `15 minutes` | |

The password hasher fragment maps the platform `User` class to the `auto` algorithm:

```php
['password_hashers' => [SolidWorx\Platform\PlatformBundle\Model\User::class => ['algorithm' => 'auto']]]
```

---

## The login page

You don't register a login route or controller — the platform does it for you. A
route loader inspects your firewall's `form_login` configuration and generates:

- a `GET` route at `form_login.login_path` (`/login`) handled by the platform's
  `Login` controller, and
- a `POST` check route at `form_login.check_path` that Symfony's form-login
  authenticator listens on.

The `Login` controller renders the template configured under
`platform.ui.templates.login`, passing the last username, the last authentication
error, and the form-login options. Override the template to brand your login page:

```yaml
# platform.yaml
platform:
  ui:
    templates:
      # Default: '@Ui/Security/login.html.twig'
      login: '@App/security/login.html.twig'
```

Because the routes are derived from the firewall config, changing `login_path` /
`check_path` (see below) automatically moves the generated routes — there is nothing
else to keep in sync.

---

## Customising

Pass an `$overrides` array — keyed by the same `security` sub-keys — and the helper
**deep-merges** it onto the platform defaults. The merge rules are:

- **associative arrays** merge by key (so you set only what you want to change);
- **scalars** are overwritten;
- **sequential lists** (like `access_control`) are **concatenated**, platform values
  first.

You only write the keys you care about — everything else stays at the platform
default. (Your IDE autocompletes the top-level keys from the method's typed signature.)

### Add custom authenticators / a default target path

```php
return App::config(LoginExtension::defaultFormLoginConfig([
    'firewalls' => [
        'main' => [
            'custom_authenticators' => [App\Security\OAuthAuthenticator::class],
            // where users land after a successful login
            'form_login' => ['default_target_path' => '_dashboard'],
        ],
    ],
]));
```

The platform's `main` firewall keeps all its other settings; only `custom_authenticators`
and `form_login.default_target_path` are added.

### Add your own user providers / password hashers / firewalls

```php
return App::config(LoginExtension::defaultFormLoginConfig([
    'password_hashers' => [
        App\Security\ApiUser::class => ['algorithm' => 'auto'],
    ],
    'providers' => [
        // `platform_user` is registered automatically; add your own here
        'api_users' => ['id' => App\Security\ApiUserProvider::class],
    ],
    'firewalls' => [
        'api' => ['pattern' => '^/api', 'stateless' => true],
    ],
]));
```

### Tune remember-me, throttling or logout

Override just the leaf keys you care about — siblings are preserved:

```php
return App::config(LoginExtension::defaultFormLoginConfig([
    'firewalls' => [
        'main' => [
            'remember_me' => ['lifetime' => 1209600], // 14 days
            'login_throttling' => ['max_attempts' => 3],
        ],
    ],
]));
```

### Add access-control rules

Access-control order matters — Symfony evaluates rules top-to-bottom and the first
match wins. Because lists **concatenate**, your rules land **after** the platform's
(e.g. the 2FA rules), so platform rules keep precedence while yours still apply:

```php
return App::config(LoginExtension::defaultFormLoginConfig([
    'access_control' => [
        ['path' => '^/admin', 'roles' => ['ROLE_ADMIN']],
        ['path' => '^/', 'roles' => ['ROLE_USER']],
    ],
]));
```

> Need to *replace* (not extend) the platform config wholesale? Skip the helper and
> write the `security` array yourself — the helper is for the common
> defaults-plus-tweaks case.

---

## Reference

```php
namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension;

final class LoginExtension
{
    /**
     * @param array{
     *     password_hashers?: array<class-string, mixed>,
     *     providers?: array<string, mixed>,
     *     firewalls?: array<string, mixed>,
     *     access_control?: list<array<string, mixed>>,
     *     role_hierarchy?: array<string, list<string>|string>,
     * } $overrides
     *
     * @return array{security: array<array-key, mixed>}
     */
    public static function defaultFormLoginConfig(array $overrides = []): array;
}
```

- `$overrides` — app-specific settings, deep-merged onto the platform defaults
  (associative merge, scalar overwrite, list concatenate).
- Two-factor authentication is **not** a parameter — it is enabled automatically from
  `platform.security.two_factor.enabled`. See
  [Two-Factor Authentication](./two-factor.md).

---

## See also

- [Two-Factor Authentication](./two-factor.md) — TOTP, email codes, backup codes and
  trusted devices.
- [Configuration](../configuration/index.md) — the `platform.yaml` reference
  (`platform.models.user`, `platform.security.two_factor`, `platform.ui.templates`).
