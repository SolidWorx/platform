### Simple card with just content

```html

<twig:Ui:Card>
    This is the card body content.
</twig:Ui:Card>
```

### Card with title and subtitle

```html

<twig:Ui:Card title="My Card" subtitle="A brief description">
    Card body content here.
</twig:Ui:Card>
```

### Card with status indicator

```html

<twig:Ui:Card status="success" statusPosition="top">
    Success card with green status bar.
</twig:Ui:Card>
```

### Card with custom header and footer

```html

<twig:Ui:Card>
    <twig:block name="header">
        <div class="d-flex justify-content-between">
            <h3 class="card-title">Custom Header</h3>
            <button class="btn btn-primary btn-sm">Action</button>
        </div>
    </twig:block>

    Main card content goes here.

    <twig:block name="footer">
        <span class="text-muted">Last updated: today</span>
    </twig:block>
</twig:Ui:Card>
```

### Small borderless card with hover effect

```html

<twig:Ui:Card size="sm" :borderless="true" :hover="true">
    Compact clickable card.
</twig:Ui:Card>
```

### Stacked card effect

```html

<twig:Ui:Card :stacked="true" title="Stacked Card">
    Creates a layered visual effect.
</twig:Ui:Card>
```