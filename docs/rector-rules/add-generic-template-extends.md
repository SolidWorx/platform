# AddGenericTemplateExtendsRector

Adds `@extends` annotations to classes that extend a generic parent class without specifying its template types.

**Class:** `SolidWorx\Platform\Tools\Rector\Rules\AddGenericTemplateExtendsRector`

## Supported Parent Classes

### Doctrine Repositories

For `ServiceEntityRepository` and `EntityRepository`, the entity class is inferred from the second argument of `parent::__construct()`.

```diff
+/**
+ * @extends ServiceEntityRepository<Plan>
+ */
 final class PlanRepository extends ServiceEntityRepository
 {
     public function __construct(ManagerRegistry $registry)
     {
         parent::__construct($registry, Plan::class);
     }
 }
```

This also works for intermediate repository base classes that declare their own `@template` tags (e.g., a custom `EntityRepository` that extends `ServiceEntityRepository<T>`).

### Symfony Form Types

For `AbstractType`, the rule uses a three-tier strategy:

#### 1. Entity-backed forms (`data_class`)

When `configureOptions()` sets a `data_class`, the entity class is used directly:

```diff
+/**
+ * @extends AbstractType<Tax>
+ */
 final class TaxType extends AbstractType
 {
     public function configureOptions(OptionsResolver $resolver): void
     {
         $resolver->setDefaults(['data_class' => Tax::class]);
     }
 }
```

Both `setDefaults(['data_class' => ...])` and `setDefault('data_class', ...)` are recognized.

#### 2. Array shape from `buildForm()`

When no `data_class` is set, the rule analyzes `$builder->add()` calls to produce a typed array shape:

```diff
+/**
+ * @extends AbstractType<array{event: string, transports: list<string>}>
+ */
 final class NotificationSettingType extends AbstractType
 {
     public function buildForm(FormBuilderInterface $builder, array $options): void
     {
         $builder->add('event', HiddenType::class);
         $builder->add('transports', ChoiceType::class, [
             'multiple' => true,
         ]);
     }
 }
```

The following Symfony form types are mapped to PHP types:

| Form Type | PHP Type |
|-----------|----------|
| `TextType`, `HiddenType`, `TextareaType`, `EmailType`, `PasswordType`, `SearchType`, `UrlType`, `TelType`, `ColorType`, `MoneyType`, `RangeType`, `EnumType` | `string` |
| `IntegerType` | `int` |
| `NumberType`, `PercentType` | `float\|int\|string` |
| `CheckboxType` | `bool` |
| `ChoiceType` | `string` |
| `ChoiceType` with `multiple: true` | `list<string>` |
| `CollectionType` | `array` |
| Custom or unrecognized types | `mixed` |

Fields with `mapped => false` are excluded from the shape.

The array shape is abandoned (falls back to `mixed`) when:

- Any field name is not a string literal (e.g., `$builder->add($options['field'], ...)`)
- No `$builder->add()` calls are found in `buildForm()`

#### 3. Fallback

When neither `data_class` nor `buildForm()` fields can be resolved:

```diff
+/**
+ * @extends AbstractType<mixed>
+ */
 final class SomeType extends AbstractType { }
```

### Symfony Voters

For `Voter`, the rule uses `string` for `TAttribute` (which is bound to `string` by Symfony's Voter class) and `mixed` for `TSubject`:

```diff
+/**
+ * @extends Voter<string, mixed>
+ */
 final class ApiAccessVoter extends Voter { }
```

## Fixing Existing Annotations

The rule also fixes existing `@extends` / `@template-extends` annotations that contain unqualified fully-qualified class names. PHPStan interprets `SomeNamespace\SomeClass` as relative to the current namespace, which causes resolution failures.

```diff
 /**
- * @extends ServiceEntityRepository<SolidInvoice\TaxBundle\Entity\Tax>
+ * @extends ServiceEntityRepository<\SolidInvoice\TaxBundle\Entity\Tax>
  */
```

When Rector's `withImportNames()` is enabled, this is further simplified to a short name with a `use` import.

## How It Works

1. The rule visits every `Class_` node with an `extends` clause.
2. It walks the parent class chain using `ReflectionClass` to determine if the parent (or any ancestor) declares `@template` tags.
3. If the child class does not already have a matching `@extends` / `@template-extends` annotation, the rule attempts to infer the generic types.
4. Only when inference succeeds with confidence does the rule emit the annotation.
