# Theming & Customization

The platform stylesheet is built on [Tabler](https://tabler.io/) (Bootstrap 5). All visual tokens — colours, spacing, typography — are controlled by SCSS variables that you can override before they are compiled.

---

## How variable injection works

The platform's webpack config checks for a custom variables file at build time. If found, its contents are prepended to every SCSS compilation via sass-loader's `additionalData` option. This means your overrides are in scope before `@tabler/core` and the platform's own styles are imported, so Bootstrap and Tabler variables resolve to your values.

---

## Providing a custom variables file

### Default location (automatic)

Create `assets/scss/_variables.scss` in your **application** (not inside the platform package). The platform config looks for this file relative to `process.cwd()` — which is your project root — so no extra configuration is needed:

```
your-app/
├── assets/
│   └── scss/
│       └── _variables.scss   ← picked up automatically
└── webpack.config.js
```

### Custom location

Set the `SOLIDWORX_PLATFORM_CUSTOM_STYLE_VARIABLES` environment variable to an absolute path:

```bash
SOLIDWORX_PLATFORM_CUSTOM_STYLE_VARIABLES=/path/to/my/_theme.scss bun run build
```

This is useful in monorepos or when your build pipeline manages asset paths.

---

## Writing your variables file

Override any Bootstrap or Tabler variable before its `!default` assignment is reached. The platform declares a small set of its own defaults that you can also override:

| Variable | Platform default | Description |
|----------|-----------------|-------------|
| `$prefix` | `'swp-'` | CSS custom-property prefix used for platform components |
| `$nav-link-font-size` | `1.2rem` | Navigation link font size |
| `$font-sans-serif` | `'Inter', sans-serif` | Body font stack |
| `$enable-deprecation-messages` | `false` | Suppress Bootstrap deprecation warnings |

Example `_variables.scss`:

```scss
// Override the CSS variable prefix used by platform components
$prefix: 'myapp-';

// Change the primary brand colour (Bootstrap variable)
$primary: #e74c3c;

// Switch to a system font stack
$font-sans-serif: system-ui, -apple-system, sans-serif;

// Tighten border radius throughout
$border-radius: 0.25rem;
$border-radius-sm: 0.125rem;
$border-radius-lg: 0.375rem;
```

Because this file is injected before any `@import`, all downstream `!default` declarations pick up your values automatically — you do not need to `@import` anything from inside `_variables.scss`.

---

## SCSS class prefix

Platform-specific components (currently the rich text editor) use BEM classes prefixed with `$prefix`. If you change `$prefix` to `'myapp-'`, the text editor toolbar class becomes `.myapp-text-editor__toolbar` instead of `.swp-text-editor__toolbar`.

The platform Twig templates read the prefix from a server-side configuration value so the generated HTML always matches the compiled CSS. You do not need to configure this separately.

---

## Adding your own styles

Add your own SCSS entries to the webpack config the same way you add JS entries:

```js
// webpack.config.js
import Encore from '@solidworx/platform/webpack.config.js';

export default Encore
    .addEntry('app', './assets/app.js')
    .addStyleEntry('admin', './assets/scss/admin.scss')
    .getWebpackConfig();
```

Inside your SCSS files you can import Tabler utilities and the platform's partials directly, since they are resolvable from `node_modules`:

```scss
// assets/scss/admin.scss
@use '@tabler/core/scss/utilities' as *;

.my-component {
    @include make-container();
}
```

---

## Adding your own Stimulus controllers

Controllers are plain JavaScript files (`.js`). Place them in `assets/controllers/` and register the directory:

```js
// assets/app.js
import { registerControllers } from '@solidworx/platform';

registerControllers(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.js$/
));
```

File name convention: `assets/controllers/my_feature_controller.js` → registered as `my-feature`.

> **Important:** Controllers in the platform package are distributed as plain JavaScript. Do not use TypeScript (`.ts`) for controllers that ship inside a package consumed by other applications — `ts-loader` excludes `node_modules` by default, so consumers cannot process them. Use native ES2022 private fields (`#field`) for encapsulation instead.
