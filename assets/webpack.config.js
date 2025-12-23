import Encore from '@symfony/webpack-encore';
import ESLintPlugin from 'eslint-webpack-plugin';

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

    /*.addStyleEntry('app', './assets/scss/app.scss')
    .addStyleEntry('email', './assets/scss/email.scss')
    .addStyleEntry('pdf', './assets/scss/pdf.scss')*/

    .enableSingleRuntimeChunk()
    .splitEntryChunks()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .enableSassLoader()
    .autoProvidejQuery()

    //.enableStimulusBridge(process.cwd() + '/assets/controllers.json')
    .enableTypeScriptLoader()

    .addPlugin(new ESLintPlugin())

    .enableIntegrityHashes(Encore.isProduction())
;

export default Encore
