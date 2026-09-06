import type { Application } from '@hotwired/stimulus';

/**
 * The Stimulus application bootstrapped by the `_platform_ui` entry.
 *
 * Applications extending the platform should reuse this instance through
 * {@link getApp} and {@link registerControllers} rather than starting a second
 * application, which would register every controller twice.
 */
export declare const app: Application;

/**
 * Register a directory of controllers on the platform's Stimulus application.
 *
 * @param context - Webpack require.context for the controllers directory
 */
export declare function registerControllers(context: __WebpackModuleApi.RequireContext): void;

/**
 * Get the platform's Stimulus application instance.
 */
export declare function getApp(): Application;
