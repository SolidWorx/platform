import './scss/platform.scss';

import '@tabler/core';

import { startStimulusApp } from '@symfony/stimulus-bridge';
import type { Application } from '@hotwired/stimulus';

import CheckboxSelectAll from '@stimulus-components/checkbox-select-all';
import PasswordVisibility from '@stimulus-components/password-visibility';
import Clipboard from '@stimulus-components/clipboard';

export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
));

app.register('checkbox-select-all', CheckboxSelectAll);
app.register('password-visibility', PasswordVisibility);
app.register('clipboard', Clipboard);

/**
 * Register additional controller directories for applications extending the platform.
 *
 * This allows custom applications to load their own controllers while maintaining
 * all platform controllers.
 *
 * @param context - Webpack require.context for the controllers directory
 *
 * @example
 * // In your application's main entry file:
 * import { registerControllers } from '@solidworx/platform';
 *
 * // Register your own controllers
 * registerControllers(require.context(
 *     '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
 *     true,
 *     /\.[jt]sx?$/
 * ));
 */
export function registerControllers(context: __WebpackModuleApi.RequireContext): void {
    app.load(context);
}

/**
 * Get the Stimulus application instance.
 *
 * This allows custom applications to register individual controllers or access
 * the Stimulus app for advanced use cases.
 *
 * @example
 * // In your application's main entry file:
 * import { getApp } from '@solidworx/platform';
 * import MyController from './controllers/my_controller';
 *
 * const app = getApp();
 * app.register('my', MyController);
 */
export function getApp(): Application {
    return app;
}
