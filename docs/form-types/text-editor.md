# TextEditorType

**Class:** `SolidWorx\Platform\PlatformBundle\Form\Type\TextEditorType`
**Parent type:** `Symfony\Component\Form\Extension\Core\Type\TextareaType`
**Stores:** sanitized HTML string (default) or a validated Tiptap JSON document

## Overview

`TextEditorType` progressively enhances a standard `<textarea>` into a rich text editor powered by
[Tiptap](https://tiptap.dev/). It is deliberately opinionated: you get a curated toolbar, sensible
defaults, and a secure pipeline out of the box, with a few options to tune behaviour.

Key properties:

- **Graceful degradation** — a real `<textarea>` is always rendered, so the field still works without JavaScript.
- **Secure by default** — every submission is re-sanitized on the server. Whatever the browser sends is
  filtered down to the allow-list for the configured toolbar, so scripts, event handlers and unsafe URL
  schemes can never be persisted. Client-side behaviour is never trusted.
- **Auto-registered** — the form type and its sanitizer are wired automatically by `PlatformBundle`.

## Requirements

The HTML sanitizer component must be installed (a dependency of the bundle):

```bash
composer require symfony/html-sanitizer
```

The frontend assets must be built so the Stimulus controller and styles are available:

```bash
cd assets
bun install
bun run build
```

The widget is rendered by the platform form theme. Register it once in your Twig configuration so the
editor markup is applied (without it, the field gracefully falls back to a plain textarea):

```yaml
# config/packages/twig.yaml
twig:
    form_themes:
        - '@Platform/Form/theme.html.twig'
```

## Usage

```php
use SolidWorx\Platform\PlatformBundle\Form\Type\TextEditorType;

$builder->add('body', TextEditorType::class, [
    'label' => 'Description',
    'required' => false,
]);
```

That's it — the field renders the editor with the default toolbar and stores sanitized HTML in the
mapped property (a plain `string`).

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `output_format` | `'html'` \| `'json'` | `'html'` | Store sanitized HTML, or a Tiptap (ProseMirror) JSON document. |
| `json_as_array` | `bool` | `false` | Only for `output_format: 'json'`. When `true`, the model value is a decoded PHP `array`; when `false`, it stays a JSON `string` (DB/column friendly). |
| `toolbar` | `'minimal'` \| `'default'` \| `'full'` | `'default'` | Toolbar preset. Drives both the buttons shown and the sanitization allow-list. |
| `allowed_tags` | `string[]` \| `null` | `null` | Override the HTML tags permitted by the sanitizer (HTML mode only). |
| `sanitizer` | `HtmlSanitizerInterface` \| `null` | `null` | Provide your own configured sanitizer instead of the platform default (HTML mode only). |
| `placeholder` | `string` \| `null` | `null` | Placeholder text shown when the editor is empty. |
| `editor_height` | `string` \| `null` | `null` | Minimum editor height as a CSS value, e.g. `'20rem'`. |

### Toolbar presets

| Preset | Features |
|--------|----------|
| `minimal` | bold, italic, link |
| `default` | headings (H2–H3), bold, italic, strikethrough, bullet/numbered lists, quote, inline code, link, undo/redo |
| `full` | everything in `default` plus H1, code block and horizontal rule |

## Choosing an output format

```php
// Sanitized HTML (default) — maps to a string property/column.
$builder->add('body', TextEditorType::class);

// Tiptap JSON stored as a string — maps to a TEXT/JSON column you decode yourself.
$builder->add('body', TextEditorType::class, [
    'output_format' => 'json',
]);

// Tiptap JSON decoded to a PHP array — maps to a Doctrine `json` column.
$builder->add('body', TextEditorType::class, [
    'output_format' => 'json',
    'json_as_array' => true,
]);
```

JSON is useful when you need structured content for custom extensions or rendering pipelines. In JSON
mode the submitted document is validated against the toolbar's allowed nodes and marks, and unsafe link
URLs are stripped.

## Rendering the output

For HTML output, the stored value is already sanitized, so it is safe to print with the `raw` filter:

```twig
<div class="content">{{ product.body|raw }}</div>
```

For JSON output, render it with your own renderer (e.g. a Tiptap/ProseMirror-to-HTML converter); the
JSON itself is not HTML and must not be printed directly.

## Security notes

- Sanitization happens in a Symfony form data transformer, i.e. on the server, independent of the
  editor. Posting hand-crafted payloads that bypass the JavaScript editor cannot inject unsafe markup.
- Links are restricted to the `http`, `https` and `mailto` schemes and are forced to carry
  `rel="noopener noreferrer"`.
- Narrow the allow-list further per field with `allowed_tags`, or pass a fully custom `sanitizer`.
