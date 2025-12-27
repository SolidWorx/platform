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

    .enableSassLoader((options) => {
        if (hasCustomVariables) {
            // Inject custom variables before all SCSS imports
            options.additionalData = `@import "${customVariablesPath}";`;
        }
    })
    .autoProvidejQuery()

    //.enableStimulusBridge(process.cwd() + '/assets/controllers.json')
    .enableTypeScriptLoader()

    .addPlugin(new ESLintPlugin())

    .enableIntegrityHashes(Encore.isProduction())
;

export default Encore
