# EnforcePlatformEntityRepositoryRector

Ensures all Doctrine repository classes extend the platform's `EntityRepository` instead of Doctrine's `ServiceEntityRepository` or `EntityRepository` directly.

**Class:** `SolidWorx\Platform\Tools\Rector\Rules\EnforcePlatformEntityRepositoryRector`

## Why

The platform's `EntityRepository` (`SolidWorx\Platform\PlatformBundle\Repository\EntityRepository`) extends Doctrine's `ServiceEntityRepository` and adds shared functionality like `save()` and `remove()` with entity type validation. All repositories in the project should use this base class to get consistent behavior.

## What It Does

The rule rewrites the `extends` clause when a repository class directly extends one of:

- `Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository`
- `Doctrine\ORM\EntityRepository`

```diff
-use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
+use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;

-final class InvoiceRepository extends ServiceEntityRepository
+final class InvoiceRepository extends EntityRepository
 {
     public function __construct(ManagerRegistry $registry)
     {
         parent::__construct($registry, Invoice::class);
     }
 }
```

No constructor changes are needed — the platform's `EntityRepository` extends `ServiceEntityRepository`, so the `parent::__construct($registry, Entity::class)` call remains valid.

## What It Skips

- **Abstract classes** — base repository classes are left alone.
- **Classes already using the platform repository** — when `EntityRepository` (or any intermediate base class that extends it) is already in the parent chain.
- **Non-repository classes** — only classes whose parent chain includes a Doctrine repository class are affected.

### Intermediate Base Repositories

The rule accepts custom base repositories as long as the platform's `EntityRepository` is somewhere in the parent chain:

```php
// This is fine — BaseRepository extends the platform's EntityRepository
abstract class BaseRepository extends EntityRepository { }

// This is also fine — InvoiceRepository inherits EntityRepository through BaseRepository
final class InvoiceRepository extends BaseRepository { }
```

## Configuration

This rule requires skipping the built-in `AddAnnotationToRepositoryRector` from the Doctrine set, which would otherwise add a conflicting `@extends ServiceEntityRepository<...>` annotation:

```php
use Rector\Doctrine\Bundle230\Rector\Class_\AddAnnotationToRepositoryRector;

return RectorConfig::configure()
    ->withRules([
        EnforcePlatformEntityRepositoryRector::class,
    ])
    ->withSkip([
        AddAnnotationToRepositoryRector::class,
    ])
    // ...
```

The `AddGenericTemplateExtendsRector` rule (also in this project) will automatically add the correct `@extends EntityRepository<Entity>` annotation after the parent is changed.

## Stale Annotation Cleanup

If a stale `@extends ServiceEntityRepository<...>` annotation already exists on the class (e.g., from a previous Rector run or manual addition), the rule rewrites it to reference `EntityRepository` instead:

```diff
 /**
- * @extends ServiceEntityRepository<Invoice>
+ * @extends EntityRepository<Invoice>
  */
 final class InvoiceRepository extends EntityRepository
```
