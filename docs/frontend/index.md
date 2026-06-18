# Frontend Assets

SolidWorx Platform ships a ready-to-use Webpack Encore configuration and a Stimulus controller library. Your application extends the platform config rather than building from scratch, so you inherit the full asset pipeline — compiled styles, Stimulus bridge, TypeScript loader, SCSS loader, ESLint, integrity hashes — with a single import.

---

## Requirements

| Tool | Minimum version |
|------|----------------|
| Node.js | 18 |
| Bun (or npm/yarn) | any recent |
| `@symfony/webpack-encore` | 5.x |

---

## Installation

Install the platform's npm package from your application's `assets/` directory:

```bash
cd assets
bun install @solidworx/platform
```

Or add it to your `package.json` manually and run `bun install`:

```json
{
  "dependencies": {
    "@solidworx/platform": "*"
  }
}
```

---

## Webpack configuration

The platform exports a pre-configured Encore instance. Import it in your `webpack.config.js` and chain your own entries on top:

```js
// webpack.config.js
import Encore from '@solidworx/platform/webpack.config.js';

export default Encore
    .addEntry('app', './assets/app.js')
    .addStyleEntry('print', './assets/scss/print.scss')
    .getWebpackConfig();
```

### What the platform config provides

The following are already configured and do **not** need to be added by your application:

| Feature | Detail |
|---------|--------|
| Output path | `public/static/` |
| Public path | `/static` |
| `_platform_ui` entry | Platform JS + Stimulus app bootstrapped from `core.ts` |
| SCSS / Sass loader | Enabled; supports custom variable injection (see [Theming](./customization.md)) |
| TypeScript loader | Enabled via `ts-loader` |
| ESLint plugin | Runs on every build |
| Single runtime chunk | Enabled |
| Entry chunk splitting | Enabled |
| Source maps | In dev builds only |
| Asset versioning | In production builds only |
| Integrity hashes | In production builds only |
| jQuery auto-provide | Enabled (required by Tabler) |
| Stimulus bridge | Enabled; see [Using the Stimulus app](#using-the-stimulus-app) below |

---

## Registering the platform's Twig asset tags

The platform output path is `/static` and the manifest file is generated automatically. Register it in your Symfony configuration so `asset()` and `importmap()` tags resolve correctly:

```yaml
# config/packages/webpack_encore.yaml
webpack_encore:
    output_path: '%kernel.project_dir%/public/static'
```

Include the platform's compiled runtime and entry in your base Twig layout:

```twig
{# templates/layout/base.html.twig #}
{{ encore_entry_script_tags('_platform_ui') }}
{{ encore_entry_link_tags('_platform_ui') }}

{# your own entries #}
{{ encore_entry_script_tags('app') }}
{{ encore_entry_link_tags('app') }}
```

---

## Using the Stimulus app

The platform bootstraps a Stimulus application in `_platform_ui` and exports it so your own entry file can register additional controllers without starting a second app.

### Registering a directory of controllers

```js
// assets/app.js
import { registerControllers } from '@solidworx/platform';

registerControllers(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.js$/
));
```

### Registering a single controller

```js
// assets/app.js
import { getApp } from '@solidworx/platform';
import MyController from './controllers/my_controller.js';

const app = getApp();
app.register('my', MyController);
```

### Enabling the Stimulus bridge for your controllers.json

If you use Symfony UX and have a `controllers.json`, chain `enableStimulusBridge` after your import:

```js
// webpack.config.js
import Encore from '@solidworx/platform/webpack.config.js';

export default Encore
    .addEntry('app', './assets/app.js')
    .enableStimulusBridge('./assets/controllers.json')
    .getWebpackConfig();
```

> **Note:** The platform's own `enableStimulusBridge` call is intentionally omitted from the platform config because the bridge must point to *your* project's `controllers.json`. Add it yourself as shown above.

---

## Building assets

From inside the `assets/` directory of your application:

```bash
# Development build (with source maps)
bun run dev

# Watch mode
bun run watch

# Production build (versioned, integrity hashes)
bun run build
```

---

## Next steps

- [Stimulus controllers reference](./controllers.md) — what each built-in controller does and how to use it
- [Theming & customization](./customization.md) — overriding SCSS variables and the custom variables file
