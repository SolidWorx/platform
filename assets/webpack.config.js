import Encore from '@symfony/webpack-encore';
import ESLintPlugin from 'eslint-webpack-plugin';

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

//we need to change up how __dirname is used for ES6 purposes
const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Allow users to override CSS variables by providing a custom variables file
// Set the SOLIDWORX_PLATFORM_CUSTOM_STYLE_VARIABLES environment variable or create a _variables.scss file
const customVariablesPath = process.env.SOLIDWORX_PLATFORM_CUSTOM_STYLE_VARIABLES || path.join(process.cwd(), 'assets/scss/_variables.scss');
const hasCustomVariables = fs.existsSync(customVariablesPath);

// @tabler/core bundles its own copy of Bootstrap's JS and re-exports it, so an
// application that also imports the standalone `bootstrap` package ends up with two
// Bootstrap instances on the page. Both register Bootstrap's data-api, which makes
// every dropdown, collapse and offcanvas toggle fire twice and so appear not to open
// at all. Point bare `bootstrap` imports at the copy Tabler ships. The trailing `$`
// matches only the exact request, leaving `bootstrap/scss/...` on the real package.
const bootstrapAlias = { bootstrap$: '@tabler/core' };

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/static/')
    // public path used by the web server to access the output path
    .setPublicPath('/static')

    .addEntry('_platform_ui', __dirname + '/core.ts')

    .addAliases(bootstrapAlias)

    .enableSingleRuntimeChunk()
    .splitEntryChunks()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .enableSassLoader((options) => {
        if (hasCustomVariables) {
            // Inject custom variables before all SCSS imports
            options.additionalData = `@import "${customVariablesPath}";`;
        }
    })
    .autoProvidejQuery()

    //.enableStimulusBridge(process.cwd() + '/assets/controllers.json')
    .enableTypeScriptLoader()

    // This package ships TypeScript sources, and package managers install it under
    // node_modules (a `file:` dependency is copied there), which Encore's TypeScript
    // rule excludes by default. Without this the sources are handed to webpack's JS
    // parser, which fails on any type annotation, as a module that throws at runtime
    // instead of a build error. Compile this package's own sources.
    .configureLoaderRule('typescript', (rule) => {
        rule.exclude = /node_modules[\\/](?!@solidworx[\\/]platform[\\/])/;
    })

    .addPlugin(new ESLintPlugin())

    .enableIntegrityHashes(Encore.isProduction())
;

export default Encore
