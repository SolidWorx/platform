// Lets the Markdown in src/content/docs keep plain relative links (./two-factor.md,
// ../../../../../src/Foo.php) so it still reads correctly on GitHub, and turns them into
// site routes or GitHub links at build time. A link to a file that does not exist fails the build.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const contentRoot = fileURLToPath(new URL('../content/docs/', import.meta.url));
const repoRoot = fileURLToPath(new URL('../../../', import.meta.url));

export default function remarkRepoLinks({ repoUrl, branch = 'main' }) {
    return (tree, file) => {
        const fromRepoRoot = !file.path.startsWith(contentRoot);

        // Files imported from the repo root (UPGRADE.md, CHANGELOG.md) bring their own H1;
        // the page that imports them supplies the title.
        if (fromRepoRoot && tree.children[0]?.type === 'heading' && tree.children[0].depth === 1) {
            tree.children.shift();
        }

        const walk = (node) => {
            if ((node.type === 'link' || node.type === 'definition') && /^\.\.?\//.test(node.url)) {
                const [rel, hash] = node.url.split('#');
                const target = path.resolve(path.dirname(file.path), rel);
                if (!fs.existsSync(target)) {
                    file.fail(`Broken link "${node.url}" (resolved to ${target})`, node);
                }
                const anchor = hash ? `#${hash}` : '';
                if (target.startsWith(contentRoot) && target.endsWith('.md')) {
                    const slug = path
                        .relative(contentRoot, target)
                        .replace(/(^|\/)index\.md$/, '')
                        .replace(/\.mdx?$/, '');
                    node.url = `/${slug ? `${slug}/` : ''}${anchor}`;
                } else {
                    node.url = `${repoUrl}/blob/${branch}/${path.relative(repoRoot, target)}${anchor}`;
                }
            }
            node.children?.forEach(walk);
        };
        walk(tree);
    };
}
