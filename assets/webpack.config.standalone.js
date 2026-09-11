// `webpack.config.js` exports the Encore *builder* so applications can extend it
// (see docs/frontend/customization.md). webpack-cli needs a finished config object,
// so this wrapper is what the package's own `build` script points at.
//
// It is only used to build (and so smoke-test) the platform on its own. Applications
// never load it — they extend the builder and call `enableStimulusBridge()` with their
// own controllers.json, which is what registers the Symfony UX controllers they use.
import path from 'path';
import { fileURLToPath } from 'url';
import Encore from './webpack.config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// `startStimulusApp()` imports `@symfony/stimulus-bridge/controllers.json` unconditionally,
// so the alias has to point somewhere even when the platform is built by itself.
Encore.enableStimulusBridge(path.join(__dirname, 'controllers.json'));

export default Encore.getWebpackConfig();
