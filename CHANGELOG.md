# Changelog

## Unreleased

### Added

- `ResetPasswordMailer`: send the password reset email from code other than the form, so
  non-form callers (an API, a console command) send exactly the same email.

### Fixed

- `PlatformConfigBuilder::withSaasConfig()` and `withUiConfig()` now emit root-level `saas` and
  `ui` sections. They used to nest them under `platform`, which failed at boot with
  `Unrecognized option "saas" under "platform"`, so a `platform.php` using them could not load.
- A `saas` or `ui` section nested under `platform:` now fails with an error saying to move it to
  the root, instead of `Unrecognized option`. The configuration docs showed the nested form; they
  now show `saas:` at the root.
- `PlanFeatureManager::setFeature()` and `removeFeature()` now clear the cached value for that
  feature. They used to delete a wildcard key, which cache pools do not support, so the old value
  kept being served from the cache until it was cleared.
