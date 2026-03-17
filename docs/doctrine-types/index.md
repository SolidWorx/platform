# Doctrine Types

SolidWorx Platform provides custom Doctrine DBAL types that map PHP value objects to database columns. These types are automatically registered when the `PlatformBundle` is loaded — no additional configuration is required.

## Available Types

| Type name | PHP class | DB column | Description |
|-----------|-----------|-----------|-------------|
| `url` | `League\Uri\Uri` | `VARCHAR` | Stores a URL as a string and maps it to a `Uri` value object |

## Usage

Reference a custom type by its `NAME` constant in an `#[ORM\Column]` attribute:

```php
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;

#[ORM\Column(type: URLType::NAME)]
private ?Uri $websiteUrl = null;
```

Using the constant (`URLType::NAME`) instead of a bare string `'url'` gives you IDE autocompletion, refactoring support, and a compile-time reference to the type class.

## Detailed Reference

- [URLType](./url-type.md) — Store URLs as `League\Uri\Uri` value objects
