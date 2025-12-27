# Modal Component

The Modal component displays content in an overlay dialog. Built on Tabler/Bootstrap 5 styles with Stimulus.js integration.

## Basic Usage

```twig
<twig:Ui:Modal id="my-modal" title="Modal Title">
    Modal body content goes here.
</twig:Ui:Modal>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | `string` | `random()` | Unique identifier for the modal |
| `title` | `string` | `''` | Title displayed in the modal header |
| `show` | `bool` | `false` | Whether the modal is initially visible |
| `size` | `string` | `''` | Modal size: `sm`, `lg`, `xl`, `full` |
| `centered` | `bool` | `false` | Vertically center the modal |
| `scrollable` | `bool` | `false` | Enable scrolling within the modal body |
| `staticBackdrop` | `bool` | `false` | Prevent closing by clicking outside |
| `closeable` | `bool` | `true` | Show close button in header |
| `status` | `string` | `''` | Status bar color: `primary`, `success`, `warning`, `danger`, etc. |

## Blocks

| Block | Description |
|-------|-------------|
| `content` | Main modal content (default block, alias for `body`) |
| `body` | Modal body content |
| `header` | Custom header content (replaces title) |
| `footer` | Footer content (buttons, actions) |
| `full_content` | Override entire modal content structure |

## Examples

### Basic Modal

```twig
<twig:Ui:Modal id="basic-modal" title="Welcome">
    <p>Thank you for visiting our application.</p>
</twig:Ui:Modal>

{# Trigger button #}
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#basic-modal">
    Open Modal
</button>
```

### Size Variants

```twig
{# Small modal #}
<twig:Ui:Modal id="small-modal" title="Small Modal" size="sm">
    This is a small modal dialog.
</twig:Ui:Modal>

{# Large modal #}
<twig:Ui:Modal id="large-modal" title="Large Modal" size="lg">
    This is a large modal with more space for content.
</twig:Ui:Modal>

{# Extra large modal #}
<twig:Ui:Modal id="xl-modal" title="Extra Large Modal" size="xl">
    This modal is extra large for complex content.
</twig:Ui:Modal>

{# Fullscreen modal #}
<twig:Ui:Modal id="full-modal" title="Fullscreen Modal" size="full">
    This modal takes up the entire screen.
</twig:Ui:Modal>
```

### Centered Modal

```twig
<twig:Ui:Modal id="centered-modal" title="Centered Modal" :centered="true">
    This modal is vertically centered in the viewport.
</twig:Ui:Modal>
```

### Scrollable Content

```twig
<twig:Ui:Modal id="scrollable-modal" title="Terms and Conditions" :scrollable="true">
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</p>
    <p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...</p>
    {# Long content that will scroll #}
</twig:Ui:Modal>
```

### Static Backdrop

```twig
<twig:Ui:Modal id="static-modal" title="Important Action" :staticBackdrop="true">
    <p>You must complete this action. Clicking outside won't close this modal.</p>
    <twig:block name="footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
            I Understand
        </button>
    </twig:block>
</twig:Ui:Modal>
```

### With Status Bar

```twig
{# Success status #}
<twig:Ui:Modal id="success-modal" title="Success" status="success">
    Your operation completed successfully.
</twig:Ui:Modal>

{# Danger status #}
<twig:Ui:Modal id="danger-modal" title="Error" status="danger">
    Something went wrong. Please try again.
</twig:Ui:Modal>

{# Warning status #}
<twig:Ui:Modal id="warning-modal" title="Warning" status="warning">
    Please review before proceeding.
</twig:Ui:Modal>
```

### Non-closeable Modal

```twig
<twig:Ui:Modal id="required-modal" title="Required Action" :closeable="false" :staticBackdrop="true">
    <p>You must complete this step before continuing.</p>
    <twig:block name="footer">
        <button type="submit" class="btn btn-primary">Complete</button>
    </twig:block>
</twig:Ui:Modal>
```

### Custom Header

```twig
<twig:Ui:Modal id="custom-header-modal">
    <twig:block name="header">
        <div class="d-flex align-items-center w-100">
            <span class="avatar me-3" style="background-image: url(/avatar.jpg)"></span>
            <div>
                <h5 class="modal-title">John Doe</h5>
                <small class="text-muted">Last seen 2 hours ago</small>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
        </div>
    </twig:block>

    <p>Custom header with avatar and additional information.</p>
</twig:Ui:Modal>
```

### With Footer Actions

```twig
<twig:Ui:Modal id="confirm-modal" title="Confirm Delete" status="danger">
    <p>Are you sure you want to delete this item? This action cannot be undone.</p>

    <twig:block name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancel
        </button>
        <button type="button" class="btn btn-danger">
            Delete
        </button>
    </twig:block>
</twig:Ui:Modal>
```

### Form in Modal

```twig
<twig:Ui:Modal id="form-modal" title="Add New User" size="lg">
    {{ form_start(form) }}
    <div class="row">
        <div class="col-md-6">
            {{ form_row(form.firstName) }}
        </div>
        <div class="col-md-6">
            {{ form_row(form.lastName) }}
        </div>
    </div>
    {{ form_row(form.email) }}

    <twig:block name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancel
        </button>
        <button type="submit" class="btn btn-primary">
            Create User
        </button>
    </twig:block>
    {{ form_end(form) }}
</twig:Ui:Modal>
```

### Combined Options

```twig
{# Large, centered, scrollable modal with status and footer #}
<twig:Ui:Modal
    id="comprehensive-modal"
    title="Review Document"
    size="lg"
    :centered="true"
    :scrollable="true"
    status="primary"
>
    <h4>Document Preview</h4>
    <p>Long document content here...</p>

    <twig:block name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Close
        </button>
        <button type="button" class="btn btn-success">
            Approve
        </button>
        <button type="button" class="btn btn-danger">
            Reject
        </button>
    </twig:block>
</twig:Ui:Modal>
```

### Initially Shown Modal

```twig
{# Useful for server-side rendered modals that should appear immediately #}
<twig:Ui:Modal id="welcome-modal" title="Welcome!" :show="true" :centered="true">
    Welcome to our application! This modal appears automatically on page load.
</twig:Ui:Modal>
```

### Custom Attributes

```twig
<twig:Ui:Modal
    id="custom-modal"
    title="Custom Modal"
    class="my-custom-modal"
    data-testid="test-modal"
    data-controller="custom-modal"
>
    Modal with custom data attributes.
</twig:Ui:Modal>
```

## JavaScript Integration

The modal uses the `@solidworx/platform/modal` Stimulus controller for enhanced functionality.

### Opening Modal Programmatically

```javascript
// Using Bootstrap's modal API
const modal = new bootstrap.Modal(document.getElementById('my-modal'));
modal.show();

// Or using data attributes
document.querySelector('[data-bs-target="#my-modal"]').click();
```

### Modal Events

```javascript
const modalEl = document.getElementById('my-modal');

modalEl.addEventListener('show.bs.modal', (event) => {
    console.log('Modal is about to show');
});

modalEl.addEventListener('shown.bs.modal', (event) => {
    console.log('Modal is now visible');
});

modalEl.addEventListener('hide.bs.modal', (event) => {
    console.log('Modal is about to hide');
});

modalEl.addEventListener('hidden.bs.modal', (event) => {
    console.log('Modal is now hidden');
});
```

## Accessibility

- Includes proper `aria-hidden` and `aria-labelledby` attributes
- Focus is trapped within the modal when open
- ESC key closes the modal (unless `staticBackdrop` is set)
- Close button includes `aria-label` for screen readers

## Related Components

- [Alert](./Alert.md) - For inline feedback messages
- [Card](./Card.md) - For displaying contained content
