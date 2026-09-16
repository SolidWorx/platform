# UI Components

The UI bundle ships Twig components for the patterns that repeat across an application. They exist
so the same kind of information looks the same everywhere: a setting on the profile page and a
setting on the two-factor page are the same component, not two pieces of markup that drift apart.

> **The rule they enforce.** Never write page-scoped styles or one-off markup for something another
> page already shows. Reach for Tabler first, then for a component here; if neither fits, add a
> component and use it everywhere the pattern appears. A restyle that lands on one page only is not
> finished.

All of them are anonymous components under `src/Bundle/Ui/templates/components/`, so they take
props, pass extra attributes through to their root element, and merge any `class` you give them.

---

## `Ui:Card`

A Tabler card. The header takes an optional icon tile, which is how a section says what it is at a
glance.

```twig
<twig:Ui:Card
    icon="tabler:shield"
    iconColor="success"
    title="{{ 'Security'|trans }}"
    subtitle="{{ 'Manage how you sign in'|trans }}"
>
    <twig:block name="content">…</twig:block>
    <twig:block name="footer">…</twig:block>
</twig:Ui:Card>
```

| Prop | Default | Description |
|------|---------|-------------|
| `title` / `subtitle` | `''` | The header text. The subtitle sits under the title. |
| `icon` | `''` | A UX icon name, e.g. `tabler:shield`. Rendered as a soft-tinted tile before the title. |
| `iconColor` | `'primary'` | Any Tabler colour — `primary`, `success`, `warning`, `danger`, … |

Plus the Tabler card variants: `status`, `statusPosition`, `stacked`, `borderless`, `size`, `hover`,
`inactive`, `rotate`, `image`, `imagePosition`, `stamp`, `progress`, `ribbon`.

Slots: `content` (inside the padded card body), `body` (replaces the body wrapper — use it when the
content is a `list-group`), `header`, `footer`.

---

## `Ui:SettingRow`

One setting: what it is, what it currently says, and the buttons that change it. Rows are
`list-group-item`s, so they go inside a `list-group list-group-flush` in a card body.

```twig
<twig:Ui:Card icon="tabler:shield" title="{{ 'Security'|trans }}">
    <twig:block name="body">
        <div class="list-group list-group-flush">
            <twig:Ui:SettingRow
                icon="tabler:mail"
                title="{{ 'Email'|trans }}"
                description="{{ 'Receive a code by email when you sign in'|trans }}"
                status="{{ 'Enabled'|trans }}"
                statusColor="success"
                statusIcon="tabler:check"
                note="{{ 'Codes are sent to %email%'|trans({'%email%': user.email}) }}"
            >
                <button type="button" class="btn btn-danger">{{ 'Disable'|trans }}</button>
            </twig:Ui:SettingRow>
        </div>
    </twig:block>
</twig:Ui:Card>
```

| Prop | Default | Description |
|------|---------|-------------|
| `title` | `''` | What the setting is. |
| `description` | `''` | One line saying what it does. |
| `icon` / `iconColor` | `''` / `'primary'` | The tinted icon tile on the left. |
| `status` / `statusColor` / `statusIcon` | `''` / `'secondary'` / `''` | A state badge under the description. |
| `note` / `noteIcon` | `''` / `''` | A small muted line under the badge. |

The default slot holds the actions. They sit right on wide screens and wrap onto their own line
when narrow.

---

## `Ui:SettingsNav`

The vertical navigation down the side of a settings section, rendered from a KnpMenu.

```twig
<twig:Ui:SettingsNav menu="profile_menu" />
```

| Prop | Default | Description |
|------|---------|-------------|
| `menu` | *required* | The KnpMenu name to render. |
| `title` | `''` | An optional card header above the entries. |

Entries come from ordinary menu builders, so a page joins a section by registering one — see
[adding a page to the profile section](../security/profile.md#adding-a-page-to-the-profile-section).
The entry matching the current route is highlighted automatically. It renders nothing when no
builder has registered the menu, so a layout can mount it unconditionally.

---

## `Ui:PasswordField`

Wraps a password `<input>` in a leading icon and a show/hide toggle. Pass the input as the content —
it has to carry the `input` Stimulus target, because only the caller knows which element is the
field.

```twig
<twig:Ui:PasswordField>
    <input type="password" class="form-control" {{ stimulus_target('password-visibility', 'input') }} />
</twig:Ui:PasswordField>
```

| Prop | Default | Description |
|------|---------|-------------|
| `icon` | `'tabler:password'` | The leading icon. Pass `''` to drop it. |
| `toggleLabel` | `'Show password'` | Accessible label on the toggle. |

**You rarely call this directly.** The platform form theme's `password_widget` already wraps every
Symfony password field in it, so any `PasswordType` in any form gets the toggle without asking. Use
the component by hand only for a password input that is not part of a Symfony form — which is what
the login page does.

---

## `Ui:Alert` and `Ui:Modal`

Tabler alerts and modals. `Ui:Alert` takes `type`, `title`, `icon`, `avatar`, `dismissible`,
`important` and `link`; `Ui:Modal` takes `id`, `title`, `size`, `centered`, `scrollable`,
`staticBackdrop`, `closeable`, `status` and `show`, with `body`, `header` and `footer` slots.

---

## A caveat about what a slot can see

A component slot compiles to a Twig `embed`, and the component being rendered becomes the one in
scope. Ordinary variables pass into the slot as you would expect, but three things do **not** mean
what they do outside it:

| Inside a slot | Resolves against | Do this instead |
|---------------|------------------|-----------------|
| `block('…')` | the component's blocks | render it into a variable first |
| `parent()` | the component's parent | render it into a variable first |
| `this` | the component the slot belongs to | read the property into a variable first |

```twig
{% set rows = block('profile_detail_rows') %}
{% set qr_image = this.qrContent %}

<twig:Ui:Card title="{{ 'Details'|trans }}">
    <twig:block name="content">
        {{ rows|raw }}
        <img src="{{ qr_image }}" alt="" />
    </twig:block>
</twig:Ui:Card>
```

This bites hardest in a live component, where `this` is how you reach the component's own methods:
`{{ this.qrContent }}` inside a `<twig:Ui:Modal>` slot asks the *modal* for a QR code and fails with
`Neither the property "qrContent" nor … exist … in class AnonymousComponent`.

---

## Next steps

- [Layouts](./layouts.md) — the page layouts these components sit inside
- [Stimulus controllers reference](./controllers.md) — the behaviour behind them
- [Theming & customization](./customization.md) — SCSS variables and brand colours
