# Stimulus Controllers

The platform registers a set of Stimulus controllers automatically when `_platform_ui` is included on the page. All controllers are **lazy-loaded** — their JavaScript is only fetched when a matching `data-controller` attribute appears in the DOM.

The following third-party controllers are also registered globally by the platform:

| Controller name | Package |
|----------------|---------|
| `checkbox-select-all` | `@stimulus-components/checkbox-select-all` |
| `password-visibility` | `@stimulus-components/password-visibility` |
| `clipboard` | `@stimulus-components/clipboard` |

---

## modal

**File:** `controllers/modal.js`

Wraps a Bootstrap `Modal` instance around the host element and keeps it in sync with the DOM.

### Behaviour

- On `connect`, creates or retrieves the Bootstrap Modal instance for the element.
- If the element already has the `show` class (e.g. server-rendered open state), the modal opens immediately.
- Listens for the global `modal:close` custom event. Dispatching that event from anywhere on the page closes the modal.

### Usage

```twig
<div data-controller="modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            ...
        </div>
    </div>
</div>
```

To close the modal programmatically from JavaScript or another controller:

```js
document.dispatchEvent(new CustomEvent('modal:close'));
```

---

## csrf-protection

**File:** `controllers/csrf_protection.js`

Implements Symfony's [SameOriginCsrfTokenManager](https://symfony.com/doc/current/security/csrf.html) double-submit cookie pattern. This controller is wired to forms automatically; you do not need to add `data-controller="csrf-protection"` yourself.

### Behaviour

- On form `submit`, generates a random CSRF token, stores the original token name in a cookie, and replaces the hidden field value with the new token.
- When Hotwired Turbo handles the submission (`turbo:submit-start`), additionally sends the token as a request header so Symfony's `check_header` option works.
- After Turbo submission completes (`turbo:submit-end`), removes the CSRF cookie.

### Exports

The module also exports three functions for advanced use:

```js
import {
    generateCsrfToken,   // (formElement) — stamp a token on the form
    generateCsrfHeaders, // (formElement) → {[name]: token} — get headers for fetch requests
    removeCsrfToken,     // (formElement) — clean up the cookie after submission
} from '@solidworx/platform/controllers/csrf_protection.js';
```

---

## loading

**File:** `controllers/loading.js`

Overlays a Bootstrap spinner on a container element while an async operation is running.

### Targets

| Target | Element | Description |
|--------|---------|-------------|
| `overlay` | `div` | The overlay element. Created automatically if absent. |

### Methods

| Method | Description |
|--------|-------------|
| `show()` | Makes the overlay visible. |
| `hide()` | Hides the overlay. |
| `onSubmit(event)` | Convenience action to show the overlay when a form is submitted. |

### Usage

```html
<div data-controller="loading">
    <!-- content -->
    <form data-action="submit->loading#onSubmit">
        <button type="submit">Save</button>
    </form>
</div>
```

The overlay is injected as an absolutely-positioned child element. The controller sets `position: relative` on the host element automatically if it was `static`.

You can also provide a custom overlay element by adding `data-loading-target="overlay"` yourself:

```html
<div data-controller="loading">
    <div data-loading-target="overlay" class="d-none ...">
        <!-- custom spinner markup -->
    </div>
</div>
```

---

## text-editor

**File:** `controllers/text_editor_controller.js`

Powers the [`TextEditorType`](../form-types/text-editor.md) form field. Mounts a [Tiptap](https://tiptap.dev/) rich text editor over a hidden `<textarea>` and keeps the two in sync so the form submits as usual.

> This controller is wired automatically by `TextEditorType` — you do not need to add it to your markup manually. See the [TextEditorType documentation](../form-types/text-editor.md) for the full form-side API.

### Values

| Value | Type | Default | Description |
|-------|------|---------|-------------|
| `output-format` | `String` | `'html'` | `'html'` stores sanitized HTML; `'json'` stores a Tiptap JSON document. |
| `placeholder` | `String` | `''` | Placeholder text shown when the editor is empty. |
| `height` | `String` | `''` | Minimum editor height as a CSS value, e.g. `'20rem'`. |

### Targets

| Target | Description |
|--------|-------------|
| `input` | The underlying `<textarea>` (hidden while the editor is active). |
| `editor` | The element where Tiptap mounts the ProseMirror view. |
| `toolbar` | Optional toolbar element. Buttons inside it are highlighted when their format is active. |

### Toolbar commands

Toolbar buttons are matched by their `data-editor-command` attribute. The controller wires these automatically; no extra JavaScript is needed.

| Command | Action |
|---------|--------|
| `bold` | Toggle bold |
| `italic` | Toggle italic |
| `strike` | Toggle strikethrough |
| `heading1` / `heading2` / `heading3` | Toggle heading levels |
| `bulletList` | Toggle bullet list |
| `orderedList` | Toggle numbered list |
| `blockquote` | Toggle blockquote |
| `code` | Toggle inline code |
| `codeBlock` | Toggle code block |
| `horizontalRule` | Insert horizontal rule |
| `link` | Toggle link (prompts for URL) |
| `undo` / `redo` | History |

### Actions

| Action | Description |
|--------|-------------|
| `run` | Executes the command specified by `data-editor-command` on the button that triggered the event. Wire to toolbar buttons via `data-action="click->text-editor#run"`. |
