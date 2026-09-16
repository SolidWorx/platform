# Stimulus Controllers

The platform registers a set of Stimulus controllers automatically when `_platform_ui` is included on the page. All controllers are **lazy-loaded** — their JavaScript is only fetched when a matching `data-controller` attribute appears in the DOM.

The following third-party controllers are also registered globally by the platform:

| Controller name | Package |
|----------------|---------|
| `checkbox-select-all` | `@stimulus-components/checkbox-select-all` |
| `password-visibility` | `@stimulus-components/password-visibility` |
| `clipboard` | `@stimulus-components/clipboard` |

> `password-visibility` is wired into the platform form theme, so every Symfony password
> field gets a show/hide toggle without any markup of your own — see
> [`Ui:PasswordField`](./components.md#uipasswordfield).

## csrf-protection

**File:** `controllers/csrf_protection.js`

Symfony's stateless (double-submit) CSRF helper. The token field is rendered holding a token *id*;
just before the form goes out, this swaps the id for a random token and writes a matching cookie,
and the server checks the pair.

It listens for the moments a form is sent: a native `submit`, Turbo's `turbo:submit-start`, and —
added by the platform — the click that triggers a **live component action**. That last one matters
because a live component never submits its form: the payload goes out by fetch from a plain
`type="button"`. Without it the field keeps the raw token id, no cookie is ever written, and every
submission from a live component fails with *"The CSRF token is invalid."*

The listener runs in the capture phase so the token is in place before the component reads the form,
and `generateCsrfToken()` fires a `change` event, which is how the component picks the new value up.
Under session-based CSRF the rendered value is already a token rather than an id, so the swap is
skipped and the whole thing is a no-op.

Nothing needs wiring: Symfony puts `data-controller="csrf-protection"` on the token field itself.

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

> Symfony forms are wired to this controller automatically — you do not need to add it to your form
> markup. See [Form Types → Loading overlay](../form-types/index.md#loading-overlay) for the `loader`
> option that turns it off per form.

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

---

## two-factor

**File:** `controllers/two_factor_controller.js`

Drives the [two-factor settings page](../security/two-factor.md): stepping through the authenticator
setup dialog, and saving backup codes to a file.

Both jobs stay in the browser deliberately. The setup dialog is two screens but one form, so the
steps are shown and hidden rather than added and removed — every field is in the DOM the whole time
and the live component always receives a complete form. And the backup codes are already rendered on
the page, so building the file client-side avoids having a route that returns recovery codes.

> This controller is mounted by the `Platform:Security:TwoFactor` component — you do not need to add
> it to your markup manually.

### Values

| Value | Type | Default | Description |
|-------|------|---------|-------------|
| `step` | `Number` | `0` | The step to show. Set by the server so a failed verification reopens on the step that failed. |
| `codes` | `Array` | `[]` | The backup codes written to the downloaded file. |
| `filename` | `String` | `'backup-codes.txt'` | Name of the downloaded file. The component sets it from the application name. |

### Targets

| Target | Description |
|--------|-------------|
| `step` | Anything belonging to one step — a pane in the body, a button in the footer. Each carries a `data-step` attribute; the ones whose `data-step` matches are shown and the rest get `d-none`. |
| `stepItem` | The entries of the Tabler `steps` indicator, in order. Only the current one gets `active`. |

Two details of that table are worth the words:

- **The step is on the element, not implied by its position.** That is what lets the footer buttons
  be siblings inside `.modal-footer`, where they pick up its spacing and right alignment, rather
  than being grouped into a wrapper per step — which would left-align the dismiss button in this
  one dialog and nowhere else.
- **Only the current `step-item` is `active`.** Tabler greys out everything *after* the active one
  via `.step-item.active ~ .step-item`, so marking the earlier steps active as well greys out the
  step the user is actually on.

### Actions

| Action | Description |
|--------|-------------|
| `next` | Advances one step, stopping at the last. |
| `previous` | Goes back one step, stopping at the first. |
| `download` | Writes `codes` to a text file and hands it to the browser. |
