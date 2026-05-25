# Custom Rector Rules

SolidWorx Platform ships custom Rector rules that add PHPStan generic type annotations to classes and methods. These rules target the `missingType.generics` PHPStan error and are safe to run automatically — they only emit annotations when the types can be inferred with high confidence.

## Available Rules

| Rule | Targets | Description |
|------|---------|-------------|
| [`AddGenericTemplateExtendsRector`](./add-generic-template-extends.md) | Class declarations | Adds `@extends` annotations to classes extending generic parents |
| [`AddGenericMethodPhpDocRector`](./add-generic-method-phpdoc.md) | Method signatures | Adds `@return` and `@param` annotations for generic return/parameter types |

## Setup

Both rules are registered in `rector.php`:

```php
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericMethodPhpDocRector;
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericTemplateExtendsRector;

return RectorConfig::configure()
    ->withRules([
        AddGenericMethodPhpDocRector::class,
        AddGenericTemplateExtendsRector::class,
    ])
    // ...
```

The `SolidWorx\Platform\Tools\` namespace is autoloaded from `src/Tools/` via `composer.json`.

## Running

```bash
# Preview changes
vendor/bin/rector process --dry-run

# Apply changes
vendor/bin/rector process
```

To run only the generic-type rules on a specific path:

```bash
vendor/bin/rector process src/Bundle/YourBundle --dry-run --clear-cache
```

> **Tip:** Use `--clear-cache` after modifying the rules themselves, otherwise Rector may skip files it considers unchanged.

## Limitations

These rules do not cover:

- **`@method` tags** on factory classes (e.g., Foundry's `FactoryCollection`) — these require understanding of the factory's generic structure and are too project-specific to automate safely.
- **Event-listener-added fields** — form fields added dynamically via `PRE_SET_DATA` or `PRE_SUBMIT` event listeners are not visible to static analysis.
- **Data transformers** — when a `DataTransformerInterface` changes the data type, the inferred type from `buildForm()` may not match the actual submitted data type.
