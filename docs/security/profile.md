# User Profile

Every signed-in user gets a **profile section**: a set of pages for maintaining their own account,
with a navigation column listing them, and a **Profile** entry in the user dropdown that leads in.

| Page | Route | Path |
|------|-------|------|
| Profile details | `solidworx_platform_profile_show` | `/profile` |
| Edit profile | `solidworx_platform_profile_edit` | `/profile/edit` |
| Change password | `solidworx_platform_profile_change_password` | `/profile/password` |
| [Two-factor authentication](./two-factor.md) | `solidworx_platform_security_two_factor_configure` | `/profile/two-factor` |

The two-factor page is only there when `platform.security.two_factor.enabled` is on; the other
three are always.

There is nothing to switch on: importing the platform routes is enough.

---

## Adding a page to the profile section

The navigation is a KnpMenu named `profile_menu`, so a page joins the section by registering an
entry — no template is edited, and nothing has to know the page exists:

```php
use Knp\Menu\ItemInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\ProfileMenu;

final class AccountMenu
{
    #[MenuBuilder(name: ProfileMenu::NAME)]
    public function build(ItemInterface $menu): void
    {
        $menu->addChild('Notifications', Options::create()->route('app_notifications')->icon('bell')->build());
        $menu->addChild('API keys', Options::create()->route('app_api_keys')->icon('key')->build());
    }
}
```

The page itself extends `profile_layout` — the Twig global, not a path — and fills one block. It
gets the navigation, the page header and the section's spacing for free:

```twig
{% extends profile_layout %}

{% block page_title %}{{ 'Notifications'|trans }}{% endblock %}

{% block profile_content %}
    <twig:Ui:Card icon="tabler:bell" title="{{ 'Email'|trans }}" subtitle="{{ 'What we send you'|trans }}">
        …
    </twig:Ui:Card>
{% endblock %}
```

Builders run from the highest priority to the lowest and each one appends, so priority decides
where entries land. The platform registers its own above the default of `0`:

| Constant | Value | Entries |
|----------|-------|---------|
| `ProfileMenu::PRIORITY_ACCOUNT` | `200` | Profile, Change password |
| `ProfileMenu::PRIORITY_SECURITY` | `100` | Two-factor authentication |

Leave the priority alone and your entries land underneath them all; register above `200` to lead
the navigation.

The entry matching the current route is highlighted automatically, by the same KnpMenu matcher the
sidebar and navbar use.

### Replacing the section's chrome

Point `platform.profile.templates.layout` at a template of your own and every page in the section
follows — the platform's and yours, since both extend the `profile_layout` global rather than a
path. Extend the shipped layout to keep the navigation and change only what differs:

```twig
{# templates/profile/layout.html.twig #}
{% extends '@SolidWorxPlatform/Profile/layout.html.twig' %}

{% block profile_nav %}
    {{ parent() }}
    <div class="mt-3">…a support link under the navigation…</div>
{% endblock %}
```

| Block | Purpose |
|-------|---------|
| `profile_nav` | The navigation column. Override with an empty block to drop it — the content then takes the full width. |
| `profile_content` | The page. This is the block a profile page fills. |

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
| `layout.html.twig` | `profile_nav`, `profile_content` |
| `show.html.twig` | `profile_initials`, `profile_name`, `profile_identity`, `profile_detail_rows`, `profile_details`, `profile_security_items`, `profile_security`, `profile_sections_extra`, plus the layout's `page_pretitle` and `page_title` |
| `edit.html.twig` | `profile_form_fields`, `profile_form_actions` |
| `change_password.html.twig` | `password_requirements`, `password_form_actions` |

`show.html.twig` also exports a `detail(label, value)` macro, so added rows keep matching the
platform's markup. Security rows are `<twig:Ui:SettingRow>` — the same component the two-factor
page uses, so a setting reads identically wherever it appears:

```twig
{% block profile_security_items %}
    {{ parent() }}

    <twig:Ui:SettingRow
        icon="tabler:key"
        title="{{ 'API keys'|trans }}"
        description="{{ 'Tokens that act on your behalf'|trans }}"
    >
        <a href="{{ path('app_api_keys') }}" class="btn">{{ 'Manage'|trans }}</a>
    </twig:Ui:SettingRow>
{% endblock %}
```

> **One caveat when overriding a block.** A `<twig:…>` component slot compiles to a Twig `embed`,
> so `block('…')`, `parent()` and `this` *inside* a slot resolve against the component, not your
> page. Read them into a variable first and print that inside the slot — which is what the shipped
> templates do. See [the full list](../frontend/components.md#a-caveat-about-what-a-slot-can-see).

### Replacing a page

The same configuration keys take an unrelated template. Each page is rendered with:

| Template | Variables |
|----------|-----------|
| `show` | `user`, `two_factor_enabled` |
| `edit` | `form`, `user` |
| `change_password` | `form`, `user`, `password_requirements` |

A replacement page still extends `profile_layout` if you want the navigation; extend anything else
and it becomes a standalone page.

---

## Customising the menu entries

Two menus are involved, and they do different jobs:

- **`user_menu`** — the dropdown behind the avatar. It carries a single **Profile** entry at
  `UserMenu::PRIORITY_PROFILE`, which leads into the section. It is deliberately not a copy of the
  section's navigation. See [the user menu](../frontend/layouts.md#the-user-menu).
- **`profile_menu`** — the navigation inside the section, covered in
  [Adding a page to the profile section](#adding-a-page-to-the-profile-section) above.

Both are ordinary KnpMenus, so both take entries through `#[MenuBuilder]`.

---

## Reference

```yaml
# platform.yaml — every profile key, with its default
platform:
  profile:
    form_type: SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType
    templates:
      layout: '@SolidWorxPlatform/Profile/layout.html.twig'
      show: '@SolidWorxPlatform/Profile/show.html.twig'
      edit: '@SolidWorxPlatform/Profile/edit.html.twig'
      change_password: '@SolidWorxPlatform/Profile/change_password.html.twig'
    password:
      min_length: 12
      strength: medium
      check_compromised: true
```
