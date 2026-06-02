# Design — `LoginExtension` security-config redesign (auto-2FA, override-merge)

**Date:** 2026-06-02
**Status:** Approved (pending spec review)
**Area:** `PlatformBundle` — security/authentication configuration helpers

## Problem

`LoginExtension::defaultFormLoginConfig(bool $enableTwoFactor = false)` returns
disjoint fragments (`password_hashers`, `firewalls`, `access_control`) that the
consuming app must manually stitch into its `config/packages/security.php`:

```php
$platform = LoginExtension::defaultFormLoginConfig(enableTwoFactor: true);

return App::config([
    'security' => [
        'password_hashers' => $platform['password_hashers'] + [/* ... */],
        'firewalls' => [
            'main' => array_replace_recursive($platform['firewalls']['main'], [/* ... */]),
        ],
        'access_control' => [...$platform['access_control'], /* ... */],
        // ...
    ],
]);
```

This leaves too much to the user: they must know which security keys exist, where the
platform fragments slot in, and how to merge each one correctly. It also requires
passing `enableTwoFactor: true` even though 2FA is already declared in `platform.yaml`
(`platform.security.two_factor.enabled`) — a second place to keep in sync.

## Goal

Make the common case a single line that follows `platform.yaml`, with overrides only
when the app has something custom:

```php
// zero-config — full platform default, 2FA follows platform.yaml
return App::config(LoginExtension::defaultFormLoginConfig());

// with app-specific tweaks
return App::config(LoginExtension::defaultFormLoginConfig([
    'firewalls' => [
        'main' => [
            'custom_authenticators' => [OAuthAuthenticator::class],
            'form_login' => ['default_target_path' => '_select_company'],
        ],
    ],
    'access_control' => [
        ['path' => '^/admin', 'roles' => ['ROLE_ADMIN']],
    ],
]));
```

## Key constraint that shapes the design

Symfony's security `firewalls` node is declared with
`disallowNewKeysInSubsequentConfigs()`. The first config that defines `firewalls`
locks the set of firewall names; no later config — including a bundle `prepend()` —
may add to it. Therefore the platform **cannot** inject the `two_factor` block into
the `main` firewall via `prepend()`; the 2FA firewall block must be part of the array
the helper returns.

Consequence: for the helper to auto-include 2FA "when `platform.yaml` enables it," the
helper itself must read the 2FA flag at the moment `security.php` is evaluated.

(`access_control`, by contrast, is a keyless prototype list — Symfony **appends**
across configs — so it merges cleanly. But since the firewall block already forces the
helper to know the flag, the helper produces the access-control rules too, for a single
source of truth.)

## Architecture

### 1. Compile-time platform config state

A process-global holder for the parsed `platform:` section, set once at Kernel boot and
read during container compilation.

```php
namespace SolidWorx\Platform\PlatformBundle\Config;

final class PlatformConfigState
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @param array<string, mixed> $config */
    public static function set(array $config): void;

    /** @return array<string, mixed>|null */
    public static function get(): ?array;

    public static function isTwoFactorEnabled(): bool; // security.two_factor.enabled === true

    public static function clear(): void;
}
```

- **Lifecycle:** set at compile time only; never read at runtime (a cached container is
  not recompiled, so `security.php` and the helper never run on a warm request).
- **Populated by** `Kernel::processPlatformConfig()`, which already runs in `boot()`
  before the container is compiled and before `config/packages/*.php` is evaluated:
  `PlatformConfigState::set($this->rawConfig['platform'] ?? [])`.
- **Cleared by** a compiler pass `ClearPlatformConfigStatePass`, registered by
  `SolidWorxPlatformBundle::build()` at `PassConfig::TYPE_AFTER_REMOVING` (the end of
  the build). Hygiene only — ensures the static does not outlive compilation.
- **Fallback:** if no platform Kernel populated the state (e.g. an isolated test
  container), `isTwoFactorEnabled()` returns `false` and the helper omits 2FA.

### 2. Redesigned `LoginExtension::defaultFormLoginConfig()`

```php
/**
 * @param array{
 *     password_hashers?: array<class-string, mixed>,
 *     providers?: array<string, mixed>,
 *     firewalls?: array<string, mixed>,
 *     access_control?: list<array<string, mixed>>,
 *     role_hierarchy?: array<string, list<string>|string>,
 * } $overrides
 *
 * @return array{security: array<string, mixed>}
 */
public static function defaultFormLoginConfig(array $overrides = []): array;
```

- Builds the default `security` payload: `password_hashers`, `firewalls.main`,
  `access_control` (empty unless 2FA).
- If `PlatformConfigState::isTwoFactorEnabled()`: folds the
  `TwoFactorExtension::securityConfig()` fragment into the result —
  `firewalls.main.two_factor` plus the 2FA `access_control` rules (resend rule then
  `^/2fa`, in that order).
- Deep-merges `$overrides` into the `security` payload (see merge semantics).
- Returns `['security' => $merged]`.
- The `enableTwoFactor` parameter is **removed**.
- `$overrides` is keyed by security sub-keys (not wrapped in `security`); the
  `@param` array-shape gives IDE autocomplete for the top-level keys.

### 3. Merge semantics

A recursive merge (`$defaults` ← `$overrides`), distinct from `array_replace_recursive`:

- **Associative arrays** (string keys / non-list): merge by key, recursing.
- **Sequential lists** (`array_is_list()`): **concatenate**, defaults first then
  overrides. This is what keeps `access_control` correct — app rules land *after* the
  platform's 2FA rules (first-match-wins precedence).
- **Scalars / mismatched types:** the override value replaces the default.

Implemented as a small private static helper in `LoginExtension` (e.g.
`mergeSecurityConfig(array $defaults, array $overrides): array`).

## Components

| Component | Type | Responsibility |
|-----------|------|----------------|
| `Config\PlatformConfigState` | new class | Hold parsed platform config at compile time; expose `isTwoFactorEnabled()`. |
| `DependencyInjection\CompilerPass\ClearPlatformConfigStatePass` | new class | Clear the state at the end of the build. |
| `Kernel::processPlatformConfig()` | edit | One line: publish the platform section to `PlatformConfigState`. |
| `SolidWorxPlatformBundle::build()` | edit | Register `ClearPlatformConfigStatePass` at `TYPE_AFTER_REMOVING`. |
| `LoginExtension::defaultFormLoginConfig()` | rewrite | New signature/return; auto-detect 2FA; merge overrides. |
| `LoginExtension::mergeSecurityConfig()` | new private | Deep-merge-with-list-concat. |
| `TwoFactorExtension::securityConfig()` | unchanged | Internal source of the 2FA fragment. |

## Data flow

```
Kernel::boot()
  └─ processPlatformConfig()           # parse platform.yaml
       └─ PlatformConfigState::set(platform section)
  └─ parent::boot()
       └─ container compile (cold only)
            └─ config/packages/security.php evaluated
                 └─ LoginExtension::defaultFormLoginConfig($overrides)
                      ├─ PlatformConfigState::isTwoFactorEnabled()?
                      │     └─ fold in TwoFactorExtension::securityConfig()
                      ├─ mergeSecurityConfig(defaults, $overrides)
                      └─ return ['security' => ...]
            └─ compiler passes
                 └─ ClearPlatformConfigStatePass → PlatformConfigState::clear()
```

## Error handling / edge cases

- **State not set** (no platform Kernel): 2FA treated as disabled. Documented
  prerequisite: extend `SolidWorx\Platform\PlatformBundle\Kernel`.
- **Override clears a list:** passing `[]` for a list key leaves the default list
  unchanged (concat). Replacing a list wholesale is out of scope — drop the helper for
  full manual control. Documented.
- **Custom firewall names in overrides:** allowed — they are part of the *first*
  `firewalls` definition (the helper's output), so `disallowNewKeysInSubsequentConfigs`
  is not triggered.

## Testing

Unit tests (no functional app available; merge correctness is exercised directly):

- `PlatformConfigState`: `set`/`get`/`clear`; `isTwoFactorEnabled()` true/false/absent.
- `defaultFormLoginConfig()` with state **off**: returns `['security' => ...]` with no
  `two_factor` key and empty `access_control`.
- `defaultFormLoginConfig()` with state **on**: includes `firewalls.main.two_factor`
  and the two 2FA `access_control` rules in order.
- Merge: scalar override replaces; new associative key added; list (`access_control`)
  appends after platform rules; nested firewall override (`form_login.default_target_path`).
- Cleanup of compile-time state between tests via `PlatformConfigState::clear()` in
  `setUp()`/`tearDown()`.

## Impact / migration

- **Breaking change** to `LoginExtension::defaultFormLoginConfig()` (signature + return
  shape; `enableTwoFactor` removed). Folded into the existing **0.3** `UPGRADE.md`
  entry.
- `docs/security/index.md` and `docs/security/two-factor.md` updated so every example
  uses `App::config(LoginExtension::defaultFormLoginConfig())` and explains that 2FA is
  driven entirely by `platform.yaml`.
- Existing `LoginExtensionTest` / `TwoFactorExtensionTest` updated for the new shape.

## Out of scope

- Auto-detecting anything other than 2FA from `platform.yaml`.
- Wholesale list replacement in overrides.
- Changing `TwoFactorExtension::enable()` or the 2FA routes/templates.
