# Two-Factor Authentication

SolidWorx Platform integrates [`scheb/2fa`](https://symfony.com/bundles/SchebTwoFactorBundle/)
to add a second authentication step after a successful password login. Turning it on
gives you, out of the box:

- **TOTP** (authenticator apps such as Google Authenticator / 1Password),
- **Email** one-time codes (with a resend endpoint),
- **Backup codes**, and
- **Trusted devices** (skip 2FA on a known device for a period).

The platform provides the user contract, the form renderers, the 2FA pages, the routes
and the firewall/access-control wiring — you opt in with two coordinated changes.

> Read [Authentication & Security](./index.md) first — 2FA builds directly on the
> default form-login firewall described there.

---

## How it fits together

A single switch drives everything: `platform.security.two_factor.enabled` in
`platform.yaml`. When it is `true`, the platform does both halves for you during
container build:

1. **The bundle integration** — registers `SchebTwoFactorBundle`, the TOTP/email
   providers, the form renderers and the 2FA routes (via `TwoFactorExtension::enable()`).

2. **The firewall wiring** — `LoginExtension::defaultFormLoginConfig()` detects the flag
   (through `PlatformConfigState`, which the kernel populates at boot) and automatically
   folds the `two_factor` block into the `main` firewall plus the 2FA access-control
   rules.

So there is **nothing 2FA-specific in `security.php`** — as long as you build your
security config from `defaultFormLoginConfig()`, flipping the flag turns 2FA on and off
wholesale.

---

## Enabling 2FA

### 1. Turn on the platform flag

```yaml
# platform.yaml
platform:
  security:
    two_factor:
      enabled: true
      # Base layout your 2FA pages extend. Optional but recommended so the
      # TOTP/email pages match your app's chrome.
      base_template: '@App/layout/base.html.twig'
```

This registers `SchebTwoFactorBundle` and the platform's TOTP and email form
renderers, and activates the 2FA routes.

### 2. Use the login helper (you already do)

The standard security config picks 2FA up automatically — no change needed:

```php
// config/packages/security.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return App::config(LoginExtension::defaultFormLoginConfig());
```

With the flag on, the `main` firewall now contains a `two_factor` block:

| Setting | Value |
|---------|-------|
| `auth_form_path` | `2fa_login` |
| `check_path` | `2fa_login_check` |
| `enable_csrf` | `true` |
| `always_use_default_target_path` | `true` |

…and the 2FA [access-control rules](#access-control) are added ahead of any rules you
pass in `$overrides`.

### 3. Make sure the user entity is 2FA-ready

The platform's abstract `User` model already implements the full 2FA contract
(`UserTwoFactorInterface`) via the `UserTwoFactor` trait, which provides the Doctrine
columns and logic for every provider:

- `totp_secret` — the TOTP shared secret,
- `email_auth_enabled` / `auth_code` — email-code state,
- `backup_codes` — generated backup codes,
- `trusted_version` — trusted-device versioning.

If your `User` extends `SolidWorx\Platform\PlatformBundle\Model\User`
([see prerequisites](./index.md#prerequisites)) you get all of this for free — just
generate a migration so the columns exist. You then enable a method per user at
runtime, for example after a user scans their TOTP QR code:

```php
$user->setTotpSecret($secret);   // enable TOTP
$user->enableEmailAuth(true);    // or enable email codes
$user->setBackUpCodes($codes);   // issue backup codes
```

`is2FaEnabled()` reports whether any second factor is active for the user.

---

## Routes

When 2FA is enabled the platform registers these routes:

| Route name | Path | Purpose |
|------------|------|---------|
| `2fa_login` | `/2fa` | The 2FA challenge form (TOTP / email / backup code). |
| `2fa_login_check` | `/2fa_check` | Where the 2FA form posts to. |
| `_solidworx_platform_security_two_factor_resend` | `/2fa/resend` | Re-send the email code, then return to the challenge. |

The challenge pages let users switch provider via
`path('2fa_login', {preferProvider: '...'})`.

---

## Access control

With 2FA enabled, `defaultFormLoginConfig()` contributes these access-control rules
(also available on their own from `TwoFactorExtension::securityConfig()`):

```php
['path' => '^/2fa/resend', 'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS']],
['path' => '^/2fa',        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS']],
```

`IS_AUTHENTICATED_2FA_IN_PROGRESS` grants access to a user who has passed the first
factor but not yet completed 2FA — exactly the people who need to reach the challenge
and resend endpoints. The first rule matches the resend-code route (`/2fa/resend`); its
path is derived from `ResendTwoFactorCode::PATH`, so the rule always matches the actual
route even if it moves. It is listed **before** `^/2fa` so the broader prefix can't
shadow it (rules are matched top-to-bottom, first match wins).

> **Ordering is handled for you.** When you add `access_control` rules through
> `defaultFormLoginConfig($overrides)`, the merge **concatenates** lists with the
> platform rules first, so the 2FA rules always precede your own. That matters because
> an early catch-all such as `['path' => '^/', 'roles' => ['ROLE_USER']]` would
> otherwise require full authentication on `/2fa` and lock users out of the challenge.

---

## Customising the 2FA pages

The platform ships default TOTP and email challenge templates. They extend the base
layout you set in `platform.security.two_factor.base_template`, so the quickest way to
make them match your app is to point that at your own layout (step 1 above).

For full control you can override the underlying scheb templates
(`@SolidWorxPlatform/Security/TwoFactor/totp.html.twig` and `email.html.twig`) using
Symfony's standard
[template overriding](https://symfony.com/doc/current/bundles/override.html#templates).

---

## Customising the provider configuration

`TwoFactorExtension::enable()` prepends a sensible `scheb_two_factor` configuration:

| Provider | Defaults applied |
|----------|------------------|
| **TOTP** | Enabled; server name & issuer set to your `platform.name`; 10-second leeway. |
| **Email** | Enabled; sender name set to `platform.name`; 6-digit codes. |
| **Backup codes** | Enabled. |
| **Trusted device** | Enabled; 30-day lifetime. |
| **Security tokens** | `UsernamePasswordToken`, `PostAuthenticationToken`. |

Because the platform *prepends* this config, you can override any of it by adding your
own `scheb_two_factor` configuration in `config/packages/`, e.g. to set the email
sender address:

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
  email:
    sender_email: no-reply@example.com
    sender_name: 'Acme'
```

Your values take precedence over the platform defaults for the keys you set.

---

## Reference

```php
namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension;

final class TwoFactorExtension
{
    /**
     * The two-factor firewall + access-control fragment.
     *
     * @return array{
     *     firewall: array{auth_form_path: string, check_path: string, enable_csrf: bool, always_use_default_target_path: bool},
     *     access_control: list<array{path: string, roles: list<string>}>,
     * }
     */
    public static function securityConfig(): array;

    /**
     * Registers SchebTwoFactorBundle, the form renderers and the scheb config.
     * Called automatically when `platform.security.two_factor.enabled` is true —
     * you normally never call this yourself.
     *
     * @param array{name: string, base_template: string} $config
     */
    public static function enable(ContainerBuilder $container, array $config = []): void;
}
```

`securityConfig()` is the internal source of the 2FA fragment that
`defaultFormLoginConfig()` folds in automatically. You only need it if you are building
your `security` config by hand instead of using the login helper:

```php
$twoFactor = TwoFactorExtension::securityConfig();

'firewalls' => [
    'main' => array_replace_recursive($yourFirewall, ['two_factor' => $twoFactor['firewall']]),
],
'access_control' => [
    ...$twoFactor['access_control'],
    // your app rules ...
],
```

---

## See also

- [Authentication & Security](./index.md) — the default form-login firewall this
  builds on.
- [Configuration](../configuration/index.md) — `platform.security.two_factor` options.
