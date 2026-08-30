# Form Types

The platform ships a small set of opinionated, reusable Symfony form types that any application can use.

## Contents

- [TextEditorType](./text-editor.md) — a Tiptap-powered rich text editor with server-side HTML sanitization.

## Form extensions

### Loading overlay

Every form is wired to the [`loading` Stimulus controller](../frontend/controllers.md#loading) so that a spinner
overlay covers the form while it is being submitted. Nothing has to be added to your templates — the
`data-controller` and `data-action` attributes are rendered onto the root `<form>` element automatically.

Only the root form is wired up. Child forms render inside the same `<form>` element, so wiring them as well
would stack an overlay onto every nested widget.

Set the `loader` option to `false` to opt a form out:

```php
$form = $this->createForm(ProfileType::class, $profile, [
    'loader' => false,
]);
```

Or, to disable it for every instance of a form type:

```php
public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefault('loader', false);
}
```

Forms that declare their own `data-controller` or `data-action` attributes keep them — the loader
attributes are appended rather than overwritten.
