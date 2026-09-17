# Upstream sources

`build-static.sh` is a **verbatim copy** from [dunglas/frankenphp](https://github.com/dunglas/frankenphp).
Never edit it: any change we need goes into `xcaddy` (which impersonates xcaddy during the build) or
into the PHP command that drives the script.

- Synced from: `dunglas/frankenphp` — record the tag or commit here when syncing
- Synced on: 2026-09-16

## Re-syncing

```bash
curl -fsSL https://raw.githubusercontent.com/dunglas/frankenphp/main/build-static.sh \
  -o src/Bundle/Platform/Resources/build/build-static.sh
chmod +x src/Bundle/Platform/Resources/build/build-static.sh
git diff src/Bundle/Platform/Resources/build/build-static.sh
```

Read the diff before committing. Two things in it affect us: the `defaultExtensions` list (parsed by
`StaticBuilder` when an application configures `php.add` or `php.remove`) and the name of the binary
the script leaves in `dist/` (consumed when the build is finalised).

## Known upstream bug: the composer.json extension gate is unreachable

`build-static.sh` (around line 154) is meant to derive the extension set from the app's own
`composer.json` when `PHP_EXTENSIONS` is unset, by checking:

```sh
if [ -n "${EMBED}" ] && [ -f "${EMBED}/composer.json" ] && [ -f "${EMBED}/composer.lock" ] && [ -f "${EMBED}/vendor/installed.json" ]; then
```

`${EMBED}/vendor/installed.json` never exists: Composer 2 writes that file to
`vendor/composer/installed.json`, one directory level different. The condition is therefore always
false, and an unset `PHP_EXTENSIONS` always falls through to the script's own `defaultExtensions`
list instead — see `docs/build/index.md`'s "How PHP extensions are decided" section for how the
platform documents that in the meantime.

This is upstream's bug, not ours — `Preflight::hasDevDependencies()` in this codebase reads the
*correct* path (`vendor/composer/installed.json`), which is how the discrepancy was noticed. Do
not patch `build-static.sh` to fix it (see the rule at the top of this file); if a future re-sync
picks up a fix for the path, `BuildOptions::resolveExtensions()`'s empty-list signal (`[]` means
"let the script decide") already does the right thing with no change needed here — only the docs
above and the `info()` text on `platform.build.php.extensions`/`add`/`remove` in
`PlatformConfiguration` would need updating back.
