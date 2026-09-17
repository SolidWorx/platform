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
