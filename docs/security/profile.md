# User Profile

Every signed-in user gets three pages for maintaining their own account, plus a **Profile**
entry in the user dropdown that leads to them:

| Page | Route | Path |
|------|-------|------|
| Profile details | `solidworx_platform_profile_show` | `/profile` |
| Edit profile | `solidworx_platform_profile_edit` | `/profile/edit` |
| Change password | `solidworx_platform_profile_change_password` | `/profile/password` |

The profile page also carries a **Security** card, which links to the change-password page and —
when `platform.security.two_factor.enabled` is on — to the
[two-factor configuration page](./two-factor.md).

There is nothing to switch on: importing the platform routes is enough.

---

## What a user can change

`ProfileType` covers the fields every platform user has:

- first name,
- last name,
- email address (which is also the sign-in identifier),
- mobile number.

The password is deliberately not part of that form. It has its own page, which asks for the
current password first.

---

## Security model

The profile routes take **no user identifier**. There is no `{id}` in the path, no `user` in the
query string and no hidden field in the form — every page acts on `getUser()`, resolved from the
session token. "Can user A edit user B's profile" is therefore not a question the code can be
asked, rather than a check that could be forgotten.

On top of that:

| Concern | How it is handled |
|---------|-------------------|
| Half-authenticated visitors | Every page requires `IS_AUTHENTICATED_FULLY`, so a remember-me cookie alone does not open them |
| Mass assignment | The form type is the boundary: `roles`, `enabled`, `verified` and `password` are not fields, so a crafted request body cannot set them |
| Cross-site request forgery | Both forms are Symfony forms, so they carry (and check) a CSRF token |
| Password changes from a stolen session | The current password is required, checked with `UserPassword` against the authenticated user |
| A leaked session cookie | The session id is rotated after a successful password change, which invalidates the old one |
| Plain-text passwords | The change-password form is unmapped; the controller hashes the new password and only the hash reaches the entity |
| Taking somebody else's email | `ProfileType` carries a `UniqueEntity` constraint on `email`, so a taken address is a validation error on the field rather than a unique-index violation at flush |

Two things the platform deliberately leaves to the application, because they are policy rather
than mechanism:

- **Verifying a changed email address.** Changing the address changes the sign-in identifier
  immediately. Add a confirmation flow if your application needs one.
- **Signing other devices out.** The session that made the change is rotated; other sessions and
  remember-me cookies are untouched.

---

## Password rules

The rules live in one place and are used twice: to validate the submission, and to render the
bullet list of requirements on the page. They cannot drift apart.

```yaml
# platform.yaml
platform:
  profile:
    password:
      # Minimum number of characters.
      min_length: 12
      # none | weak | medium | strong | very_strong — an entropy estimate, not a
      # character-class checklist, so a long passphrase scores well on its own.
      strength: medium
      # Reject passwords that appear in a known breach (haveibeenpwned range API).
      # Skipped when the API cannot be reached, so an outage never blocks a rotation.
      check_compromised: true
```

For a rule the configuration does not cover, decorate the policy:

```php
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(PasswordPolicyInterface::class)]
final readonly class CompanyPasswordPolicy implements PasswordPolicyInterface
{
    public function __construct(
        #[AutowireDecorated]
        private PasswordPolicyInterface $inner,
    ) {
    }

    public function constraints(): array
    {
        return [...$this->inner->constraints(), new NotEqualTo(value: 'password')];
    }

    public function requirements(): array
    {
        return [...$this->inner->requirements(), 'Not literally "password"'];
    }
}
```

---

## Customising the form

Applications almost always configure their own user class under `platform.models.user`, so the
profile form has two extension points — pick the smaller one that fits.

### Adding fields to the platform form

A form type extension keeps the platform fields, their labels and the unique-email constraint,
and appends yours:

```php
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProfileTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ProfileType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('jobTitle', TextType::class, ['required' => false]);
    }
}
```

Nothing else is needed — the extension is autoconfigured.

### Replacing the form outright

When the shape of the form itself is different, name your own type:

```yaml
# platform.yaml
platform:
  profile:
    form_type: App\Form\ProfileType
```

The type is built with the signed-in user as its data, so it only has to set `data_class` to your
user class. Carry the `UniqueEntity` constraint over from `ProfileType` — it is what stops one
user from taking another's email address — and keep roles, the enabled flag and the password out
of it.

---

## Customising the pages

### Overriding blocks

The shipped templates are built out of blocks so that the common case — showing one more field —
does not mean owning the whole page. Extend the template and point the configuration at yours:

```yaml
# platform.yaml
platform:
  profile:
    templates:
      show: '@App/profile/show.html.twig'
```

```twig
{# templates/profile/show.html.twig #}
{% extends '@SolidWorxPlatform/Profile/show.html.twig' %}
{% import '@SolidWorxPlatform/Profile/show.html.twig' as profile %}

{% block profile_detail_rows %}
    {{ parent() }}
    {{ profile.detail('Job title'|trans, user.jobTitle) }}
{% endblock %}
```

The blocks each page exposes:

| Template | Blocks |
|----------|--------|
| `show.html.twig` | `profile_initials`, `profile_name`, `profile_identity`, `profile_detail_rows`, `profile_details`, `profile_security_items`, `profile_security`, `profile_sections_extra`, plus the layout's `page_pretitle`, `page_title` and `page_title_actions` |
| `edit.html.twig` | `profile_form_fields`, `profile_form_actions` |
| `change_password.html.twig` | `password_requirements`, `password_form_actions` |

`show.html.twig` also exports two macros — `detail(label, value)` and
`security_item(title, description, url, action, icon)` — so added rows keep matching the
platform's markup.

### Replacing a page

The same configuration keys take an unrelated template. Each page is rendered with:

| Template | Variables |
|----------|-----------|
| `show` | `user`, `two_factor_enabled` |
| `edit` | `form`, `user` |
| `change_password` | `form`, `user`, `password_requirements` |

---

## Customising the menu entry

The **Profile** entry is an ordinary KnpMenu item on the `user_menu` menu, registered at
`UserMenu::PRIORITY_PROFILE` so it leads the dropdown. Application entries default to priority
`0` and therefore land underneath it — see
[the user menu](../frontend/layouts.md#the-user-menu) for adding your own.

---

## Reference

```yaml
# platform.yaml — every profile key, with its default
platform:
  profile:
    form_type: SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType
    templates:
      show: '@SolidWorxPlatform/Profile/show.html.twig'
      edit: '@SolidWorxPlatform/Profile/edit.html.twig'
      change_password: '@SolidWorxPlatform/Profile/change_password.html.twig'
    password:
      min_length: 12
      strength: medium
      check_compromised: true
```
