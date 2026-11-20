# SolidWorx Platform docs

The documentation site, built with [Astro Starlight](https://starlight.astro.build) and served
as static assets from Cloudflare Workers.

## Run it locally

```bash
cd docs
bun install
bun run dev        # http://localhost:4321, reloads on save
bun run build      # production build into dist/, fails on a broken link
bun run preview    # build, then serve dist/ through the Workers runtime
```

## Write a page

1. Add a `.md` (or `.mdx`) file under `src/content/docs/`. The path is the URL:
   `security/two-factor.md` → `/security/two-factor/`.
2. Start it with frontmatter; the `title` is the page's H1, so do not add a `# Heading`:

   ```md
   ---
   title: Two-Factor Authentication
   description: One sentence for search results and link previews.
   ---
   ```

3. Add the page to `sidebar` in `astro.config.mjs`.
4. Link to other pages with relative paths to the file — `[Profile](./profile.md#overriding-blocks)`,
   or to source with `../../../../src/...`. They work on GitHub and are rewritten to site URLs at
   build time. A link to a file that does not exist fails the build.

Starlight's [asides](https://starlight.astro.build/guides/authoring-content/#asides)
(`:::note`, `:::caution`, `:::tip`) and [components](https://starlight.astro.build/components/using-components/)
(tabs, cards, steps; `.mdx` only) are available.

`UPGRADE.md` and `CHANGELOG.md` at the repo root are rendered at `/upgrade/` and `/changelog/`.

## Deployment

`.github/workflows/docs.yml` builds every PR that touches the docs, and deploys to Cloudflare on
every push to `main`. It needs, in the GitHub repository settings:

| Name | Kind | Value |
|------|------|-------|
| `CLOUDFLARE_API_TOKEN` | secret | API token with the *Edit Cloudflare Workers* template |
| `CLOUDFLARE_ACCOUNT_ID` | secret | Your Cloudflare account ID |
| `DOCS_SITE_URL` | variable | Public URL, e.g. `https://docs.example.com` (canonical links and sitemap) |

To deploy by hand: `bunx wrangler login`, then `bun run deploy`.
