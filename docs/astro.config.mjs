// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import remarkRepoLinks from './src/plugins/remark-repo-links.mjs';

const repoUrl = 'https://github.com/SolidWorx/platform';

export default defineConfig({
    // Canonical URLs and the sitemap need the public URL. Set DOCS_SITE_URL in CI
    // (a GitHub repository variable) once the custom domain is known.
    site: process.env.DOCS_SITE_URL,
    trailingSlash: 'always',
    markdown: {
        remarkPlugins: [[remarkRepoLinks, { repoUrl }]],
    },
    integrations: [
        starlight({
            title: 'SolidWorx Platform',
            description:
                'Documentation for SolidWorx Platform: Symfony bundles for authentication, 2FA, multi-tenancy, subscriptions and a Tabler UI.',
            social: [{ icon: 'github', label: 'GitHub', href: repoUrl }],
            editLink: { baseUrl: `${repoUrl}/edit/main/docs/` },
            lastUpdated: true,
            customCss: ['./src/styles/custom.css'],
            sidebar: [
                {
                    label: 'Getting started',
                    items: ['getting-started/installation', 'getting-started/quick-start', 'configuration'],
                },
                {
                    label: 'Security',
                    items: [
                        { label: 'Authentication', slug: 'security' },
                        'security/two-factor',
                        'security/reset-password',
                        'security/profile',
                        'security/access-decision',
                        { label: 'Encrypted columns', slug: 'encryption' },
                    ],
                },
                {
                    label: 'Multi-tenancy',
                    items: [{ label: 'Overview', slug: 'multi-tenancy' }],
                },
                {
                    label: 'SaaS & subscriptions',
                    items: ['saas/plan-features'],
                },
                {
                    label: 'Frontend',
                    items: [
                        { label: 'Assets & Webpack', slug: 'frontend' },
                        'frontend/layouts',
                        'frontend/controllers',
                        'frontend/customization',
                        {
                            label: 'UI components',
                            items: [
                                { label: 'Overview', slug: 'frontend/components' },
                                { label: 'Alert', slug: 'frontend/components/alert' },
                                { label: 'Card', slug: 'frontend/components/card' },
                                { label: 'Modal', slug: 'frontend/components/modal' },
                            ],
                        },
                    ],
                },
                {
                    label: 'Forms & Doctrine',
                    items: [
                        { label: 'Form types', slug: 'form-types' },
                        'form-types/text-editor',
                        'form-types/country',
                        { label: 'Doctrine types', slug: 'doctrine-types' },
                        'doctrine-types/url-type',
                    ],
                },
                {
                    label: 'Tooling',
                    items: [
                        { label: 'Rector rules', slug: 'rector-rules' },
                        'rector-rules/add-generic-template-extends',
                        'rector-rules/add-generic-method-phpdoc',
                        'rector-rules/enforce-platform-entity-repository',
                    ],
                },
                {
                    label: 'Releases',
                    items: ['upgrade', 'changelog'],
                },
            ],
        }),
    ],
});
