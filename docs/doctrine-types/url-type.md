# URLType

**Class:** `SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType`
**Type name:** `url` (`URLType::NAME`)
**DB column:** `VARCHAR` (inherits from Doctrine's `StringType`)
**PHP value object:** `League\Uri\Uri`

## Overview

`URLType` is a custom Doctrine DBAL type that transparently converts between a plain database string and a [`League\Uri\Uri`](https://uri.thephpleague.com/) value object. This lets you work with strongly-typed URI objects in your domain model while the database stores a plain string.

The type is registered automatically by `PlatformBundle` — you do not need to add it to your Doctrine configuration manually.

## Requirements

The `league/uri` package must be installed:

```bash
composer require league/uri
```

## Adding a URL column to an entity

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use League\Uri\Uri;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;

#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    // Nullable URL column
    #[ORM\Column(type: URLType::NAME, nullable: true)]
    private ?Uri $websiteUrl = null;

    // Required URL column
    #[ORM\Column(type: URLType::NAME)]
    private Uri $documentationUrl;

    public function getWebsiteUrl(): ?Uri
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?Uri $websiteUrl): void
    {
        $this->websiteUrl = $websiteUrl;
    }

    public function getDocumentationUrl(): Uri
    {
        return $this->documentationUrl;
    }

    public function setDocumentationUrl(Uri $documentationUrl): void
    {
        $this->documentationUrl = $documentationUrl;
    }
}
```

## Working with Uri objects

The `League\Uri\Uri` class is immutable. Use `Uri::new()` to construct one from a string:

```php
use League\Uri\Uri;

// Construct from a string
$uri = Uri::new('https://example.com/path?query=value#fragment');

// Persist via Doctrine — the type converts it to a plain string automatically
$product->setWebsiteUrl($uri);
$entityManager->flush();

// Read back — Doctrine reconstructs the Uri object automatically
$product = $entityManager->find(Product::class, $id);
$uri = $product->getWebsiteUrl(); // instanceof League\Uri\Uri

// Use the value object
echo $uri->getHost();   // example.com
echo $uri->getScheme(); // https
echo $uri->getPath();   // /path
echo (string) $uri;     // https://example.com/path?query=value#fragment
```

## Behaviour

| Scenario | `convertToPHPValue` result | `convertToDatabaseValue` result |
|----------|--------------------------|--------------------------------|
| `null` | `null` | `null` |
| Valid URL string | `Uri` object | — |
| `Uri` object | same `Uri` object (pass-through) | URL string |
| Any other type | throws `InvalidType` | throws `InvalidType` |

## Nullable vs. required columns

Use `nullable: true` on the column when the URL is optional. Omit it (or set `nullable: false`) when the URL is required. Doctrine enforces the constraint at the schema level; the type handles the `null` case on both sides.

```php
// Optional
#[ORM\Column(type: URLType::NAME, nullable: true)]
private ?Uri $websiteUrl = null;

// Required
#[ORM\Column(type: URLType::NAME)]
private Uri $canonicalUrl;
```

## Generating a migration

After adding a `URLType` column, generate and run a migration as usual:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The column will be created as `VARCHAR(255)` by default. You can override the length with the `length` parameter:

```php
#[ORM\Column(type: URLType::NAME, length: 2048)]
private Uri $longUrl;
```
