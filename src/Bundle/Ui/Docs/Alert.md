# Alert Component

The Alert component displays contextual feedback messages for typical user actions. Built on Tabler/Bootstrap 5 styles.

## Basic Usage

```twig
<twig:Ui:Alert>
    This is an informational alert.
</twig:Ui:Alert>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `type` | `string` | `'info'` | Alert color variant: `primary`, `secondary`, `success`, `info`, `warning`, `danger` |
| `title` | `string` | `''` | Optional title displayed above the content |
| `icon` | `string` | `''` | Icon identifier for UX Icons (e.g., `tabler:info-circle`) |
| `avatar` | `string` | `''` | URL to an avatar image to display instead of an icon |
| `dismissible` | `bool` | `false` | Whether the alert can be dismissed |
| `important` | `bool` | `false` | Makes the alert more prominent with solid background |
| `link` | `string` | `''` | Makes the entire alert clickable, linking to the given URL |

## Blocks

| Block | Description |
|-------|-------------|
| `content` | Main alert content (default block) |

## Examples

### Alert Types

```twig
{# Primary alert #}
<twig:Ui:Alert type="primary">
    This is a primary alert.
</twig:Ui:Alert>

{# Success alert #}
<twig:Ui:Alert type="success">
    Your changes have been saved successfully.
</twig:Ui:Alert>

{# Warning alert #}
<twig:Ui:Alert type="warning">
    Please review your input before proceeding.
</twig:Ui:Alert>

{# Danger alert #}
<twig:Ui:Alert type="danger">
    An error occurred. Please try again.
</twig:Ui:Alert>
```

### With Title

```twig
<twig:Ui:Alert type="info" title="Did you know?">
    You can customize the appearance of alerts using various props.
</twig:Ui:Alert>

<twig:Ui:Alert type="success" title="Account Created">
    Welcome! Your account has been created successfully.
</twig:Ui:Alert>
```

### With Icon

```twig
<twig:Ui:Alert type="success" icon="tabler:check" title="Success">
    Your file has been uploaded.
</twig:Ui:Alert>

<twig:Ui:Alert type="warning" icon="tabler:alert-triangle">
    Your session will expire in 5 minutes.
</twig:Ui:Alert>

<twig:Ui:Alert type="danger" icon="tabler:x">
    Failed to connect to the server.
</twig:Ui:Alert>
```

### With Avatar

```twig
<twig:Ui:Alert type="info" avatar="/path/to/avatar.jpg" title="John Doe">
    Sent you a new message.
</twig:Ui:Alert>
```

### Dismissible Alerts

```twig
<twig:Ui:Alert type="info" :dismissible="true">
    Click the X to dismiss this alert.
</twig:Ui:Alert>

<twig:Ui:Alert type="warning" :dismissible="true" title="Notice">
    This warning can be dismissed once acknowledged.
</twig:Ui:Alert>
```

### Important (Solid Background)

```twig
<twig:Ui:Alert type="primary" :important="true">
    This is an important primary message.
</twig:Ui:Alert>

<twig:Ui:Alert type="danger" :important="true" title="Critical Error">
    The system encountered a critical error.
</twig:Ui:Alert>

<twig:Ui:Alert type="success" :important="true" icon="tabler:check">
    Operation completed successfully!
</twig:Ui:Alert>
```

### Clickable Alert (Link)

```twig
<twig:Ui:Alert type="info" link="/notifications" title="3 New Notifications">
    Click to view all notifications.
</twig:Ui:Alert>

<twig:Ui:Alert type="warning" link="/billing" icon="tabler:credit-card">
    Your payment method is about to expire.
</twig:Ui:Alert>
```

### Combined Options

```twig
{# Dismissible success alert with icon and title #}
<twig:Ui:Alert
    type="success"
    icon="tabler:check-circle"
    title="Order Confirmed"
    :dismissible="true"
>
    Your order #12345 has been confirmed and is being processed.
</twig:Ui:Alert>

{# Important danger alert with custom attributes #}
<twig:Ui:Alert
    type="danger"
    :important="true"
    icon="tabler:alert-octagon"
    class="mb-4"
    data-testid="error-alert"
>
    Unable to process your request. Please contact support.
</twig:Ui:Alert>
```

### Custom Styling

```twig
{# Add custom classes #}
<twig:Ui:Alert type="info" class="shadow-sm mb-4">
    This alert has a shadow and bottom margin.
</twig:Ui:Alert>

{# With custom data attributes #}
<twig:Ui:Alert
    type="warning"
    data-controller="auto-dismiss"
    data-auto-dismiss-delay-value="5000"
>
    This alert will auto-dismiss after 5 seconds.
</twig:Ui:Alert>
```

## Accessibility

- The component includes `role="alert"` for screen reader support
- Dismissible alerts include proper `aria-label` on the close button
- Icons are decorative and properly hidden from assistive technologies

## Related Components

- [Card](./Card.md) - For displaying content in a contained box
- [Modal](./Modal.md) - For displaying overlay dialogs
