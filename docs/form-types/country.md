# CountryType

**Class:** `Symfony\Component\Form\Extension\Core\Type\CountryType` (Symfony's built-in type)
**Extended by:** `SolidWorx\Platform\PlatformBundle\Form\Extension\CountryTypeExtension`
**Stores:** ISO 3166-1 alpha-2 country code, e.g. `'ZA'`

## Overview

The platform does not ship its own country type. Instead it extends Symfony's `CountryType`, so every
country field in your application — including ones in third-party forms — becomes a searchable
[tom-select](https://tom-select.js.org/) dropdown with a [Tabler flag](https://docs.tabler.io/ui/plugins/flags)
next to each country. Users start typing a country name and the list filters as they type.

Key properties:

- **Zero configuration** — use `CountryType` as normal; the extension is auto-registered by `PlatformBundle`.
- **Graceful degradation** — a real `<select>` is always rendered, so the field still works without JavaScript.
- **Same data** — the submitted value is still the plain country code; nothing about validation or
  persistence changes.

## Requirements

`CountryType` needs the Symfony Intl component for its country list:

```bash
composer require symfony/intl
```

The frontend assets must be built so the `country-select` Stimulus controller, the tom-select styles and
the Tabler flag styles are available:

```bash
cd assets
bun install
bun run build
```

## Usage

```php
use Symfony\Component\Form\Extension\Core\Type\CountryType;

$builder->add('country', CountryType::class, [
    'label' => 'Country',
    'placeholder' => 'Select a country',
]);
```

All of Symfony's `CountryType` options (`alpha3`, `preferred_choices`, `multiple`, `placeholder`, …) keep
working. Multiple selection renders as a tom-select tag input with a flag on every selected country.

> With `alpha3: true` the values are three-letter codes, which Tabler's flag classes do not cover. The
> dropdown stays searchable, but no flags are shown.

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `searchable` | `bool` | `true` | Enhance the field with tom-select and flags. Set to `false` to render a plain `<select>`. |

The enhancement is also skipped automatically when `expanded` is `true`, since radios and checkboxes cannot
be turned into a dropdown.

```php
// Plain native <select>, no tom-select or flags.
$builder->add('country', CountryType::class, [
    'searchable' => false,
]);
```

## How it works

The extension adds `data-controller="solidworx--platform--country-select"` to the `<select>`. The controller
mounts tom-select over it and renders each option with a `flag flag-xs flag-country-{code}` element. Any
`data-controller` you pass through `attr` is kept — the platform controller is appended, not overwritten.
