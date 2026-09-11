# Theming & Customization

The platform stylesheet is built on [Tabler](https://tabler.io/) 1.5, which bundles Bootstrap 5.3 (there is no separate `bootstrap` dependency). Visual tokens — colours, spacing, typography — are `--tblr-*` CSS custom properties you override at runtime; a small number of build-time settings live in a single Sass configuration block.

---

## How theming works

Tabler is configured through the Sass module system. `assets/scss/platform.scss` loads it with
a single `@use ... with (...)` block, and that block is the only place a Sass variable can be
set — assigning a variable *before* the `@use` has no effect at all under Sass modules.

```scss
@use '@tabler/core/scss/tabler' with (
    $font-google: 'Inter',
    $font-family-sans-serif: ('Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif),
    $nav-link-font-size: 1.2rem,
    $enable-deprecation-messages: false
);
```

> The old `SOLIDWORX_PLATFORM_CUSTOM_STYLE_VARIABLES` / `assets/scss/_variables.scss` injection
> hook has been removed. It worked by prepending a file before `@import`, which Sass modules do
> not support. Use the CSS custom properties below instead.

---

## Overriding tokens from your application

Override the CSS custom properties. Every visual token Tabler exposes is a `--tblr-*`
variable, so this needs no Sass and applies at runtime:

```scss
// assets/scss/admin.scss
:root {
    --tblr-primary: #e74c3c;
    --tblr-border-radius: 0.25rem;
    --tblr-bg-surface: #fff;
}
```

Tabler 1.5 removed the Sass variables behind most of these tokens in favour of the custom
properties, so this is the supported route rather than a workaround. For the few settings with
no CSS variable (the font stack, feature flags such as `$enable-deprecation-messages`), edit the
`@use ... with (...)` block above.

---

## The `--tblr-` prefix is added by PostCSS

Tabler's Sass sources write custom properties *unprefixed* (`--card-bg`, `--border-width`).
The public `--tblr-` prefix is added after Sass runs, by
[postcss-prefix-custom-properties](https://www.npmjs.com/package/postcss-prefix-custom-properties),
configured in `webpack.config.js`. Two consequences:

- In SCSS compiled through this pipeline, **write custom properties unprefixed**
  (`var(--border-color)`, not `var(--tblr-border-color)`) — the prefix is added for you.
  Names already starting with `--tblr-` are left alone, so reading `var(--tblr-primary)`
  in your own stylesheet works too.
- A custom property of your own (`--my-thing`) compiled through this pipeline becomes
  `--tblr-my-thing`. Name your own tokens `--tblr-…` if you want them left untouched.

There is no platform-specific `$prefix` any more: the compiled CSS uses Tabler's own `--tblr-`
names, so snippets copied from the Tabler docs work as-is.

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
