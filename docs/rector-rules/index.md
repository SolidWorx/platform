# Custom Rector Rules

SolidWorx Platform ships custom Rector rules that enforce architectural conventions and add PHPStan generic type annotations. These rules are safe to run automatically and target common `missingType.generics` PHPStan errors.

## Available Rules

| Rule | Targets | Description |
|------|---------|-------------|
| [`EnforcePlatformEntityRepositoryRector`](./enforce-platform-entity-repository.md) | Repository classes | Enforces that repositories extend the platform's `EntityRepository` |
| [`AddGenericTemplateExtendsRector`](./add-generic-template-extends.md) | Class declarations | Adds `@extends` annotations to classes extending generic parents |
| [`AddGenericMethodPhpDocRector`](./add-generic-method-phpdoc.md) | Method signatures | Adds `@return` and `@param` annotations for generic return/parameter types |

## Setup

All rules are bundled into a single set that can be included in any `rector.php`:

```php
use SolidWorx\Platform\Tools\Rector\Set\SolidWorxSetList;

return RectorConfig::configure()
    ->withSets([
        SolidWorxSetList::PLATFORM,
        // ... other sets
    ])
    // ...
```

The set registers all custom rules and skips conflicting built-in rules (e.g., `AddAnnotationToRepositoryRector`).

### Manual Registration

If you prefer to register rules individually instead of using the set:

```php
use Rector\Doctrine\Bundle230\Rector\Class_\AddAnnotationToRepositoryRector;
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericMethodPhpDocRector;
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericTemplateExtendsRector;
use SolidWorx\Platform\Tools\Rector\Rules\EnforcePlatformEntityRepositoryRector;

return RectorConfig::configure()
    ->withRules([
        EnforcePlatformEntityRepositoryRector::class,
        AddGenericMethodPhpDocRector::class,
        AddGenericTemplateExtendsRector::class,
    ])
    ->withSkip([
        // Conflicts with EnforcePlatformEntityRepositoryRector — our rules
        // handle the @extends annotation with the correct parent class.
        AddAnnotationToRepositoryRector::class,
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
