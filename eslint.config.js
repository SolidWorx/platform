import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['**/node_modules/**', '**/public/static/**', '**/vendor/**'],
    },
    {
        // Browser code: Stimulus controllers and the package entry point.
        files: ['assets/controllers/**/*.js'],
        ...js.configs.recommended,
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: globals.browser,
        },
    },
    {
        // Build configuration runs under Node.
        files: ['assets/webpack.config*.js', 'eslint.config.js'],
        ...js.configs.recommended,
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: globals.node,
        },
    },
];
