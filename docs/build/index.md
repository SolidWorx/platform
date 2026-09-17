# Building a Static Binary

`platform:build` (alias `platform:compile`) compiles your application into one self-contained executable — no PHP-FPM, no separate web server process, no `vendor/` to ship. The binary bundles the FrankenPHP/Caddy server, a statically-compiled PHP runtime, your application code, and Symfony Messenger worker support, and runs standalone on the target machine.

Builds are **host-only**. There is no cross-compilation and no `--os`/`--arch` option: the vendored `build-static.sh` script that drives the actual compile always targets the machine it runs on (`uname -s`/`uname -m`). To produce a Linux binary, run the command on a Linux host or in Linux CI — an Apple Silicon Mac cannot build a `linux-x86_64` binary.

---

## Requirements

The command needs these tools on `PATH`:

| Tool | Used for |
|------|----------|
| `git` | resolving the version stamped into the binary |
| `go` | compiling and linking the final binary |
| `composer` | already required to build the application itself |
| `curl` | |
| `jq` | |
| `tar` | archiving the application |
| `bash` | running the vendored build script |

Everything deeper than that — autoconf, bison, cmake, and the rest of what compiling PHP from source needs — is installed automatically by static-php-cli's own `doctor --auto-fix`, so there's no second list of build dependencies to keep in sync here.

The **first** build downloads and compiles a static PHP runtime from source, which takes roughly **30–60 minutes**. Every build after that reuses `work_dir` (see [Configuration reference](#configuration-reference) below) and only rebuilds what changed, usually in minutes. That persistence is the entire point of `work_dir` — don't delete it between builds unless you deliberately want to pay the cold-start cost again.

---

## Quick start

The archive embedded in the binary is your checkout **exactly as it stands** — the command does not install dependencies or build your frontend assets for you. Get the application production-ready first, then build:

```bash
composer install --no-dev --optimize-autoloader
bun run build
php bin/console platform:build
```

The finished binary is written to `output_dir` (`build/` by default) as `<binary_name>-<os>-<arch>`, for example `build/my-app-mac-arm64`.

---

## Configuration reference

All build options live under `platform.build` in `platform.yaml`:

| Key | Default | Purpose |
|---|---|---|
| `name` | `platform.name` | Display name the binary reports |
| `description` | `''` | One-line description shown at startup |
| `binary_name` | slug of `name` | File name of the produced binary |
| `env_prefix` | upper-cased `binary_name` | Prefix for the binary's environment variables |
| `default_port` | `'8080'` | Port used when none is given |
| `output_dir` | `%kernel.project_dir%/build` | Where the finished binary is written |
| `work_dir` | `%kernel.project_dir%/var/build` | Persistent build cache |
| `php.version` | `null` | PHP version to compile; `null` resolves the latest |
| `php.extensions` | `[]` | Explicit extension list — see [How PHP extensions are decided](#how-php-extensions-are-decided) |
| `php.add` | `[]` | Extensions appended to the resolved set |
| `php.remove` | `[]` | Extensions subtracted from the resolved set |
| `php.extension_libs` | `['libavif', 'nghttp2', 'nghttp3', 'ngtcp2']` | Extension libraries to build |
| `exclude` | `['node_modules/', 'var/cache/', 'var/log/', '.git/', 'tests/']` | Paths kept out of the embedded application archive |
| `hooks.install_check` | `null` | Console command that exits 0 when the app is installed; workers wait for it before consuming |
| `hooks.on_boot` | `['cache:clear']` | Console commands run at every startup |

`binary_name` and `env_prefix` cascade from `name`: a name of `'My App'` slugs to `my-app`, which upper-cases (hyphens become underscores) to `MY_APP` as the default environment variable prefix.

```yaml
# platform.yaml
platform:
  build:
    description: 'Acme invoicing, compiled'
    default_port: '9000'
    exclude:
      - 'node_modules/'
      - 'var/cache/'
      - 'var/log/'
      - '.git/'
      - 'tests/'
      - 'storage/'
    hooks:
      install_check: 'app:installed'
      on_boot:
        - 'cache:clear'
        - 'doctrine:migrations:migrate --no-interaction'
```

A few things about `exclude` that are easy to get wrong:

- It **replaces** the default list rather than adding to it. The example above repeats every
  default entry it still wants (`node_modules/`, `var/cache/`, `var/log/`, `.git/`, `tests/`)
  alongside its own addition (`storage/`) — drop one of those from your own list and it stops
  being excluded.
- `.git`, `var/cache`, `var/log` and `node_modules` are excluded **no matter what** `exclude`
  says — they're hard-coded in the archiver, not merely defaults you can override away.
- `work_dir` is excluded automatically too, because that's where the archive itself is staged
  while it's being written.
- `output_dir` is **not** excluded automatically. The default (`%kernel.project_dir%/build`) sits
  inside the project, so a second build will happily embed the binary from your *first* build
  into the next archive unless you add `output_dir` to `exclude` yourself.

Both `work_dir` and `output_dir` should be in your `.gitignore` — `work_dir` because it becomes a
multi-gigabyte build cache once PHP is compiled, `output_dir` because it holds binaries, not
source.

### How PHP extensions are decided

Leave `php.extensions`, `php.add` and `php.remove` **all empty** (or omit `php` entirely) and
`build-static.sh` derives the extension set from your application's `composer.json` on its own —
the easiest option, and the right one for most applications:

```yaml
platform:
  build:
    php: {} # or omit the key entirely — composer.json drives the extension set
```

The moment **any one** of the three is set, that automatic detection turns off — `build-static.sh`
only derives from `composer.json` when its extension list is left unset, and there is no hook to
post-process that result afterwards. The base then becomes:

- your own `php.extensions` list, if you set one, otherwise
- the `defaultExtensions` list vendored inside `build-static.sh` itself (around 60 extensions,
  from `amqp` to `zstd`),

with `php.add` and `php.remove` applied on top of whichever base that is. Two examples:

```yaml
# Compile only these four extensions — nothing from composer.json, nothing from
# build-static.sh's own defaults.
platform:
  build:
    php:
      extensions: ['pdo_pgsql', 'mbstring', 'sodium', 'opcache']
```

```yaml
# extensions is left empty, so the base is build-static.sh's defaultExtensions list
# (NOT composer.json) — with mongodb added and imagick dropped.
platform:
  build:
    php:
      add: ['mongodb']
      remove: ['imagick']
```

### Restricted characters

A handful of config values get baked into the binary via `go build -ldflags -X`, through the
vendored `xcaddy` shim, which wraps each one in single quotes before handing it to `go build`. An
apostrophe in one of these values closes that quote early and corrupts every flag written after
it, so the command rejects these characters up front — before any build work starts — rather than
letting the failure surface at the very end of an hour-long build:

- `'` (apostrophe) — actually breaks the quoting
- `"`, `\`, newline, carriage return — can't break the quoting on their own, but are rejected too
  as cheap insurance against whatever the next upstream change to the quoting does

The check applies to `build.name`, `build.description`, `build.default_port`, `build.env_prefix`,
the resolved build version (from `--app-version` or the git tag/branch/commit), the
`hooks.install_check` command, and every command in `hooks.on_boot`. An entirely ordinary value
such as a company name — `"Pierre's Invoicing"` — will be refused for the apostrophe; rename it or
work around it before building.

---

## Command options

```
php bin/console platform:build [options]
```

| Option | Purpose |
|---|---|
| `--app-version=VERSION` | Version stamped into the binary. Defaults to the current git tag, branch name, or short commit hash. |
| `-o, --output=DIR` | Overrides `output_dir` for this run. |
| `--php-version=VERSION` | Overrides `php.version` for this run. |
| `--clean` | Wipes the build's own staged cache before building — see below. |
| `--skip-checks` | Skips the *application* sanity checks described in [What ends up in the binary](#what-ends-up-in-the-binary). The tool checks (git, go, composer, curl, jq, tar, bash) always run regardless. |
| `--dry-run` | Runs the checks and builds the application archive for real, then reports what *would* be built — without compiling. |

There is no `--version` option. Symfony's console `Application` reserves `--version`/`-V`
globally for its own use, and a command cannot register a conflicting option under that name —
hence `--app-version`.

**What `--clean` actually does:** it deletes the build's own staged directory under `work_dir` —
the compiled PHP, the downloaded static-php-cli sources, everything — forcing the next build to
start cold (another 30–60 minutes). It deliberately does **not** run `build-static.sh`'s own
accompanying `go clean -cache`: upstream's `CLEAN` variable wipes the **machine-wide** Go build
cache shared by every Go project on the box, and this command has no business doing that to a
developer's machine. If you're comparing this against `build-static.sh` directly, expect `--clean`
to be narrower than upstream's `CLEAN=1`.

---

## What ends up in the binary

The embedded archive is your checkout exactly as it stands, minus `exclude` — the command does not
run `composer install` or build your frontend assets for you. Before building, it checks:

- **`vendor/autoload_runtime.php` exists** — fails the build if dependencies aren't installed.
- **`public/build/manifest.json` exists** — fails the build if frontend assets haven't been built.
- **whether dev dependencies are present** (`vendor/composer/installed.json`'s `dev` flag) — warns
  but doesn't block, since a plain `composer install` without `--no-dev` would otherwise silently
  embed your entire dev toolchain in the shipped binary.

So a production build is:

```bash
composer install --no-dev --optimize-autoloader
bun run build
php bin/console platform:build
```

`--skip-checks` skips only these three application checks — the tool checks the command itself
needs (git, go, composer, curl, jq, tar, bash) always run.

---

## CI usage

```yaml
# Illustrative — adapt to your CI provider
- name: Cache the build work directory
  uses: actions/cache@v4
  with:
    path: var/build
    key: platform-build-${{ runner.os }}-${{ hashFiles('composer.lock') }}

- name: Build the binary
  run: php bin/console platform:build --app-version="${{ github.ref_name }}" -v
```

- **Cache `work_dir`** (`var/build` by default) between runs. Without it, every CI build pays the
  30–60 minute cold-start cost; with it, later builds finish in minutes.
- **Pass `--app-version` explicitly.** A CI checkout is frequently a detached HEAD or a shallow
  clone, and the version resolver falls back to a timestamp (`dev-YmdHis`) once it can't establish
  a tag, branch, or commit — not what you want stamped into a release binary.
- **Use `-v`.** Without it, the 30–60 minute PHP compile produces no console output at all, which
  some CI providers treat as a hung job and cancel. `-v` streams `build-static.sh`'s own output
  into the job log.

---

## Re-syncing `build-static.sh`

`build-static.sh` is a vendored, verbatim copy of upstream's script — nothing platform-specific
belongs in it. Anything this platform needs lives in the `xcaddy` shim or in the environment
variables the command sets around the script instead. See
[`src/Bundle/Platform/Resources/build/UPSTREAM.md`](../../src/Bundle/Platform/Resources/build/UPSTREAM.md)
for how to re-sync it and what to check in the diff.
