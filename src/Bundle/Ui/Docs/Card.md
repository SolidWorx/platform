# Card Component

The Card component is a flexible container for displaying content with optional header, footer, images, and various decorative elements. Built on Tabler/Bootstrap 5 styles.

## Basic Usage

```twig
<twig:Ui:Card>
    This is the card body content.
</twig:Ui:Card>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | `''` | Card title displayed in header |
| `subtitle` | `string` | `''` | Subtitle displayed under title |
| `status` | `string` | `''` | Status bar color: `primary`, `secondary`, `success`, `info`, `warning`, `danger` |
| `statusPosition` | `string` | `'top'` | Status bar position: `top`, `start`, `bottom` |
| `stacked` | `bool` | `false` | Creates a stacked card effect |
| `borderless` | `bool` | `false` | Removes card border |
| `size` | `string` | `''` | Card padding: `sm`, `md`, `lg` |
| `hover` | `bool` | `false` | Adds hover effect for clickable cards |
| `inactive` | `bool` | `false` | Dims the card |
| `rotate` | `string` | `''` | Slight rotation: `left`, `right` |
| `image` | `string` | `''` | Image URL |
| `imagePosition` | `string` | `'top'` | Image position: `top`, `bottom`, `overlay` |
| `imageAlt` | `string` | `''` | Image alt text |
| `stamp` | `string` | `''` | Icon identifier for stamp decoration |
| `stampColor` | `string` | `'secondary'` | Stamp background color |
| `progress` | `int\|null` | `null` | Progress bar percentage (0-100) |
| `progressColor` | `string` | `'primary'` | Progress bar color |
| `ribbon` | `string` | `''` | Ribbon text |
| `ribbonColor` | `string` | `'primary'` | Ribbon background color |
| `ribbonPosition` | `string` | `''` | Ribbon position: `top`, `start`, `bottom` |

## Blocks

| Block | Description |
|-------|-------------|
| `content` | Main card content (default block) |
| `body` | Card body content (alias for content) |
| `header` | Custom header content |
| `footer` | Footer content |
| `full_content` | Override entire card structure |

## Examples

### Simple Card

```twig
<twig:Ui:Card>
    <p>This is a simple card with just body content.</p>
</twig:Ui:Card>
```

### With Title and Subtitle

```twig
<twig:Ui:Card title="Card Title" subtitle="A brief description">
    Main card content goes here.
</twig:Ui:Card>

<twig:Ui:Card title="User Profile">
    <p>Welcome back, John!</p>
</twig:Ui:Card>
```

### Size Variants

```twig
{# Small padding #}
<twig:Ui:Card title="Compact Card" size="sm">
    Less padding for compact layouts.
</twig:Ui:Card>

{# Medium padding (default) #}
<twig:Ui:Card title="Standard Card" size="md">
    Standard padding for most use cases.
</twig:Ui:Card>

{# Large padding #}
<twig:Ui:Card title="Spacious Card" size="lg">
    More padding for emphasis.
</twig:Ui:Card>
```

### Status Indicators

```twig
{# Top status bar #}
<twig:Ui:Card title="Active Project" status="success">
    This project is currently active.
</twig:Ui:Card>

{# Left status bar #}
<twig:Ui:Card title="Pending Review" status="warning" statusPosition="start">
    Waiting for approval.
</twig:Ui:Card>

{# Bottom status bar #}
<twig:Ui:Card title="Critical Issue" status="danger" statusPosition="bottom">
    Immediate attention required.
</twig:Ui:Card>
```

### With Images

```twig
{# Image at top (default) #}
<twig:Ui:Card image="/images/photo.jpg" imageAlt="Nature photo" title="Beautiful Landscape">
    A stunning view of the mountains.
</twig:Ui:Card>

{# Image at bottom #}
<twig:Ui:Card
    image="/images/product.jpg"
    imageAlt="Product image"
    imagePosition="bottom"
    title="Featured Product"
>
    Check out our latest offering.
</twig:Ui:Card>

{# Image overlay #}
<twig:Ui:Card
    image="/images/hero.jpg"
    imageAlt="Hero image"
    imagePosition="overlay"
    class="text-white"
>
    Content overlaid on the image.
</twig:Ui:Card>
```

### With Stamp

```twig
<twig:Ui:Card stamp="tabler:star" stampColor="yellow" title="Featured">
    This is a featured item.
</twig:Ui:Card>

<twig:Ui:Card stamp="tabler:bell" stampColor="primary" title="Notifications">
    You have 3 new notifications.
</twig:Ui:Card>

<twig:Ui:Card stamp="tabler:chart-bar" stampColor="success" title="Analytics">
    View your performance metrics.
</twig:Ui:Card>
```

### With Progress Bar

```twig
{# Basic progress #}
<twig:Ui:Card title="Upload Progress" :progress="65">
    Uploading files...
</twig:Ui:Card>

{# Colored progress #}
<twig:Ui:Card title="Project Completion" :progress="85" progressColor="success">
    Almost done!
</twig:Ui:Card>

{# Warning state #}
<twig:Ui:Card title="Disk Usage" :progress="90" progressColor="warning">
    Storage is almost full.
</twig:Ui:Card>
```

### With Ribbon

```twig
{# Basic ribbon #}
<twig:Ui:Card ribbon="New" ribbonColor="primary" title="Latest Feature">
    Check out our newest addition.
</twig:Ui:Card>

{# Sale ribbon #}
<twig:Ui:Card ribbon="50% Off" ribbonColor="danger" title="Special Offer">
    Limited time discount!
</twig:Ui:Card>

{# Positioned ribbon #}
<twig:Ui:Card ribbon="Beta" ribbonColor="info" ribbonPosition="top" title="Beta Feature">
    This feature is in beta testing.
</twig:Ui:Card>
```

### Stacked Cards

```twig
<twig:Ui:Card :stacked="true" title="Stacked Card">
    Creates a layered visual effect behind the card.
</twig:Ui:Card>
```

### Borderless Cards

```twig
<twig:Ui:Card :borderless="true" title="Clean Card">
    A card without visible borders.
</twig:Ui:Card>
```

### Hover Effect

```twig
<twig:Ui:Card :hover="true" title="Clickable Card">
    <p>Hover to see the effect.</p>
    <a href="/details" class="stretched-link"></a>
</twig:Ui:Card>
```

### Inactive State

```twig
<twig:Ui:Card :inactive="true" title="Disabled Feature">
    This feature is not available.
</twig:Ui:Card>
```

### Rotated Cards

```twig
<div class="d-flex gap-3">
    <twig:Ui:Card rotate="left" title="Tilted Left">
        Slight left rotation.
    </twig:Ui:Card>

    <twig:Ui:Card rotate="right" title="Tilted Right">
        Slight right rotation.
    </twig:Ui:Card>
</div>
```

### Custom Header

```twig
<twig:Ui:Card>
    <twig:block name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <h3 class="card-title mb-0">Projects</h3>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary">Add</button>
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
        </div>
    </twig:block>

    <p>List of projects goes here.</p>
</twig:Ui:Card>
```

### With Footer

```twig
<twig:Ui:Card title="Article Preview">
    <p>This is a preview of the article content...</p>

    <twig:block name="footer">
        <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted">Published 2 days ago</span>
            <a href="/article/123" class="btn btn-primary btn-sm">Read More</a>
        </div>
    </twig:block>
</twig:Ui:Card>
```

### Combining Features

```twig
{# Feature card with stamp, status, and progress #}
<twig:Ui:Card
    title="Storage Usage"
    subtitle="Current plan: Pro"
    status="primary"
    stamp="tabler:database"
    stampColor="primary"
    :progress="75"
    progressColor="primary"
>
    <p class="h1 mb-0">75 GB</p>
    <p class="text-muted">of 100 GB used</p>

    <twig:block name="footer">
        <a href="/upgrade" class="btn btn-primary w-100">Upgrade Storage</a>
    </twig:block>
</twig:Ui:Card>
```

```twig
{# Product card with image, ribbon, and footer #}
<twig:Ui:Card
    image="/products/laptop.jpg"
    imageAlt="Laptop"
    ribbon="Sale"
    ribbonColor="danger"
    title="Gaming Laptop"
    subtitle="High Performance"
>
    <p class="h3">$1,299</p>
    <p class="text-muted text-decoration-line-through">$1,599</p>

    <twig:block name="footer">
        <button class="btn btn-primary w-100">Add to Cart</button>
    </twig:block>
</twig:Ui:Card>
```

```twig
{# User card with all features #}
<twig:Ui:Card
    status="success"
    statusPosition="top"
    :stacked="true"
    size="lg"
>
    <twig:block name="header">
        <div class="d-flex align-items-center">
            <span class="avatar avatar-lg me-3" style="background-image: url(/avatar.jpg)"></span>
            <div>
                <h3 class="card-title mb-0">John Doe</h3>
                <small class="text-muted">Senior Developer</small>
            </div>
        </div>
    </twig:block>

    <div class="row text-center">
        <div class="col">
            <div class="h3 mb-0">127</div>
            <small class="text-muted">Projects</small>
        </div>
        <div class="col">
            <div class="h3 mb-0">1.2k</div>
            <small class="text-muted">Commits</small>
        </div>
        <div class="col">
            <div class="h3 mb-0">98%</div>
            <small class="text-muted">Success</small>
        </div>
    </div>

    <twig:block name="footer">
        <div class="d-flex gap-2">
            <a href="/profile/john" class="btn btn-primary flex-fill">View Profile</a>
            <a href="/message/john" class="btn btn-outline-secondary">Message</a>
        </div>
    </twig:block>
</twig:Ui:Card>
```

### Grid of Cards

```twig
<div class="row row-cards">
    {% for project in projects %}
        <div class="col-md-6 col-lg-4">
            <twig:Ui:Card
                title="{{ project.name }}"
                subtitle="{{ project.client }}"
                status="{{ project.isActive ? 'success' : 'secondary' }}"
                :progress="{{ project.completion }}"
            >
                <p>{{ project.description|u.truncate(100) }}</p>
                <twig:block name="footer">
                    <a href="{{ path('project_show', {id: project.id}) }}">View Details</a>
                </twig:block>
            </twig:Ui:Card>
        </div>
    {% endfor %}
</div>
```

## Custom Styling

```twig
{# With custom classes #}
<twig:Ui:Card class="shadow-lg mb-4" title="Elevated Card">
    Card with custom shadow and margin.
</twig:Ui:Card>

{# With data attributes #}
<twig:Ui:Card
    data-controller="collapsible-card"
    data-testid="dashboard-card"
    title="Interactive Card"
>
    Card with custom data attributes.
</twig:Ui:Card>
```

## Accessibility

- Semantic HTML structure with proper heading hierarchy
- Progress bars include `aria-valuenow`, `aria-valuemin`, and `aria-valuemax`
- Images require `imageAlt` for screen reader support
- Hidden decorative elements (stamps, ribbons) are properly marked

## Related Components

- [Alert](./Alert.md) - For feedback messages
- [Modal](./Modal.md) - For overlay dialogs
