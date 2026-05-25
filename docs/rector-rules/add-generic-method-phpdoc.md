# AddGenericMethodPhpDocRector

Adds `@return` and `@param` PHPDoc annotations to methods whose return types or parameters reference generic classes/interfaces without specifying their template types.

**Class:** `SolidWorx\Platform\Tools\Rector\Rules\AddGenericMethodPhpDocRector`

## Supported Types

| Class / Interface | Template Parameters | Default Fill |
|-------------------|--------------------:|--------------|
| `Symfony\Component\Form\FormInterface` | `TData` | `mixed` |
| `Symfony\Component\Form\FormTypeInterface` | `TData` | `mixed` |
| `Symfony\Component\Form\FormTypeExtensionInterface` | `TData` | `mixed` |
| `Doctrine\ORM\Query` | `TKey, TResult` | `mixed, mixed` |
| `Doctrine\ORM\Mapping\ClassMetadata` | `T` | `object` |

## Return Types

When a method's PHP return type is one of the supported generic types and no `@return` tag with generic parameters exists:

```diff
+/**
+ * @return FormInterface<mixed>
+ */
 protected function instantiateForm(): FormInterface
 {
     return $this->createForm(SomeType::class);
 }
```

Nullable return types are handled:

```diff
+/**
+ * @return ?FormInterface<mixed>
+ */
 public function getForm(): ?FormInterface
 {
     // ...
 }
```

## Parameters

When a method parameter's PHP type is one of the supported generic types and no matching `@param` tag exists:

```diff
+/**
+ * @param FormInterface<mixed> $form
+ */
 public function processForm(FormInterface $form): void
 {
     // ...
 }
```

Multiple parameters on the same method are handled independently — only parameters that reference a known generic type get an annotation.

### Doctrine Examples

```diff
+/**
+ * @param Query<mixed, mixed> $query
+ */
 public function paginate(Query $query): array
 {
     // ...
 }
```

```diff
+/**
+ * @param ClassMetadata<object> $metadata
+ * @return ClassMetadata<object>
+ */
 public function processMetadata(ClassMetadata $metadata): ClassMetadata
 {
     // ...
 }
```

## Skipping Logic

The rule will **not** add an annotation when:

- The method already has a `@return` or `@param` tag with generic type parameters (e.g., `@return FormInterface<array{name: string}>`).
- The method's PHP return type or parameter type does not match any of the supported generic types.
- A docblock with generic annotations exists near the method but is misplaced (e.g., between an attribute and the method keyword) — the rule detects this by scanning the source text around the method declaration.

## Extending with New Types

To add support for additional generic types, add entries to the `GENERIC_TYPE_DEFAULTS` constant in the rule class:

```php
private const array GENERIC_TYPE_DEFAULTS = [
    FormInterface::class => ['mixed'],
    // Add new entries here:
    'App\\Some\\GenericInterface' => ['mixed'],
];
```

The array value is the list of default type parameters to use, one per template parameter on the generic class.
