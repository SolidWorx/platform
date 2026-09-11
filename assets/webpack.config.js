import Encore from '@symfony/webpack-encore';
import ESLintPlugin from 'eslint-webpack-plugin';
import prefixCustomProperties from 'postcss-prefix-custom-properties';

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

import path from 'path';
import { fileURLToPath } from 'url';

//we need to change up how __dirname is used for ES6 purposes
const __dirname = path.dirname(fileURLToPath(import.meta.url));

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/static/')
    // public path used by the web server to access the output path
    .setPublicPath('/static')

    .addEntry('_platform_ui', __dirname + '/core.ts')

    .enableSingleRuntimeChunk()
    .splitEntryChunks()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .enableSassLoader()

    // Tabler's Sass sources write custom properties unprefixed (`--card-bg`); the public
    // `--tblr-` prefix is added here, at build time, because Tabler 1.5 removed the
    // `$prefix` Sass variable. Without this step nothing resolves `--tblr-*`.
    .enablePostCssLoader((options) => {
        // `config: false` stops postcss-loader searching for a postcss.config.js in the
        // consuming application, which would otherwise silently replace this plugin list.
        options.postcssOptions = {
            config: false,
            plugins: [
                prefixCustomProperties({
                    prefix: 'tblr-',
                    // Vendor stylesheets read their own variable names — never prefix these.
                    ignore: [
                        /^--tblr-/,
                        // Applications own their design tokens: leave any already-namespaced property alone.
                        /^--swp-/,
                        /^--bs-/,
                        /^--fc-/,
                        /^--gl-/,
                        /^--litepicker-/,
                        /^--plyr-/,
                        /^--ts-/,
                        '--section-bg',
                    ],
                }),
            ],
        };
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
