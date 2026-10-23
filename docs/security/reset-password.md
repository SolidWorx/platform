# Password Reset

SolidWorx Platform ships a complete "forgot password" flow, built on
[`symfonycasts/reset-password-bundle`](https://github.com/SymfonyCasts/reset-password-bundle).
Switch it on in `platform.yaml` and you get, with no code of your own:

- a **"Forgot password?" link** on the login page and the change-password page;
- a page asking for the **email address** of the account;
- a **"check your email"** page that reads the same whether or not the account exists;
- an **email** with a single-use link that expires after **1 hour**;
- a page to **choose the new password**, held to the same password rules as the
  change-password page;
- **per-user throttling** (one link every 15 minutes) and a **per-IP rate limit**
  (5 requests every 15 minutes);
- the routes, the database mapping and the public access rule.

> Read [Authentication & Security](./index.md) first. Password reset builds on the default
> form-login firewall described there.

---

## Enabling it

1. **Switch it on** in `platform.yaml`:

   ```yaml
   platform:
     security:
       reset_password:
         enabled: true
   ```

2. **Create the table.** The `reset_password_request` table is only mapped while the
   feature is on:

   ```bash
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```

3. **Give your emails a sender** if you haven't already. Symfony refuses to send an
   email without a `From` address, and the platform doesn't guess one:

   ```yaml
   # config/packages/mailer.yaml
   framework:
       mailer:
           dsn: '%env(MAILER_DSN)%'
           headers:
               from: 'Acme <noreply@acme.test>'
   ```

4. **Pin down the host the link points at.** The link in the email is built from the
   request's host. If nothing checks that host, an attacker can request a reset for
   someone else's address with a forged `Host` header, and the victim receives a genuine
   link pointing at the attacker's site. Do one of these:

   ```yaml
   # config/packages/framework.yaml: Symfony rejects requests for any other host.
   # Use this with domain-based multi-tenancy, so each tenant's link keeps its own domain.
   framework:
       trusted_hosts: ['^app\.acme\.test$', '^.+\.acme\.test$']
   ```

   ```yaml
   # platform.yaml: every emailed link starts with this URL, whatever the request said.
   # Applies to the email verification links too.
   platform:
     security:
       base_url: 'https://app.acme.test'
   ```

   Outside debug mode, the reset pages refuse to run (with an error naming both options)
   until one of them is set. In debug mode the request host is used as is, so a local
   setup works without either. Console commands and workers are not affected: they have
   no `Host` header, and use `framework.router.default_uri`.

5. **Clear the cache.** The platform configuration decides which bundles and routes
   exist, so it is baked into the container:

   ```bash
   php bin/console cache:clear
   ```

That's it. The login page now shows **Forgot password?** next to the password label.

### If Composer added the bundle's recipe

Symfony Flex installs a bundle's recipe even when the bundle only arrives as a platform
dependency. You may find these in your app; **remove both**:

- `SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle::class` in
  `config/bundles.php`. The platform kernel registers the bundle itself, and only while
  the feature is enabled. Left in `bundles.php`, it loads even when the feature is off.
- `config/packages/reset_password.yaml`. The platform configures the bundle from
  `platform.security.reset_password`.

If you leave them, the feature still works. The kernel skips registering the bundle a
second time, and the recipe's placeholder repository is replaced with the platform's. A
repository of your own is never replaced.

---

## The flow

| Step | Route | Path |
|------|-------|------|
| Ask for a link | `_solidworx_platform_reset_password_request` | `GET/POST /reset-password` |
| "Check your email" | `_solidworx_platform_reset_password_check_email` | `GET /reset-password/check-email` |
| Choose a new password | `_solidworx_platform_reset_password` | `GET/POST /reset-password/reset/{token}` |

1. The user enters their email address. If an **enabled** account has that address and no
   link was sent to it within `throttle_limit`, a link is emailed.
2. The user always lands on "check your email", whatever happened. The page shows how long
   a link stays valid.
3. The link opens the reset page. On that first request the token is moved into the session
   and the user is redirected to `/reset-password/reset`, so the token does not leak through
   the `Referer` header or the browser history.
4. Once a valid new password is submitted:
   - it is hashed with the configured password hasher;
   - every outstanding link of the user is invalidated;
   - the email address is marked **verified**, since opening the link proves the user
     reads mail there;
   - the user is sent to the login page with a success message.

The user is **not** signed in automatically. They sign in through the login form, so
two-factor authentication still applies.

Changing the password also ends the user's other sessions and remember-me cookies. Symfony
notices the password hash changed when it refreshes the user.

### What the user sees when something goes wrong

| Situation | Result |
|-----------|--------|
| Unknown or disabled address | Same "check your email" page; no email. |
| A link was sent less than `throttle_limit` ago | Same page; no new email. The earlier link still works. |
| Mail server down, or no sender configured | Same page; the error is logged and the request discarded, so the user can try again straight away. |
| More than `rate_limit.limit` requests from one IP | The form shows "Too many password reset requests" (HTTP 429). |
| Expired link | Back to the request form with "The link in your email is expired". |
| Invalid or already-used link | Back to the request form with "The reset password link is invalid". |
| Password breaks the rules | The form shows why (HTTP 422); the link stays usable. |

The first three rows look identical on purpose. The form must not reveal which addresses
have an account. The rate limit is the only failure reported, because it says nothing
about any account.

---

## Configuration

Every key, with its default:

```yaml
platform:
  security:
    reset_password:
      # Turns the whole feature on. While off, the bundle is not registered, the routes do
      # not exist, the table is not mapped and the links are not shown.
      enabled: false

      # How long, in seconds, a reset link stays valid (minimum 60).
      lifetime: 3600

      # How long, in seconds, before another link is sent to the same user.
      throttle_limit: 900

      # Delete requests that expired more than a week ago whenever a new one is made.
      # Turn off and schedule `php bin/console reset-password:remove-expired` instead.
      enable_garbage_collection: true

      # The firewall whose login page links here, and where the user signs in afterwards.
      firewall: main

      # Per-IP limit on the request form, whichever addresses are entered.
      rate_limit:
        enabled: true
        limit: 5
        interval: '15 minutes'

      templates:
        request: '@SolidWorxPlatform/ResetPassword/request.html.twig'
        check_email: '@SolidWorxPlatform/ResetPassword/check_email.html.twig'
        reset: '@SolidWorxPlatform/ResetPassword/reset.html.twig'

      email:
        template: '@SolidWorxPlatform/ResetPassword/email.html.twig'
        # Translated before sending.
        subject: 'Reset your password'
```

The host of the emailed link comes from `platform.security.base_url` (shared with email
verification), or from the request when that is null. See step 4 of
[Enabling it](#enabling-it).

The new password is validated by the same `PasswordPolicyInterface` as the change-password
page, so `platform.profile.password` (minimum length, strength, breach check) applies to
both. See [User Profile](./profile.md).

### Access control

`LoginExtension::defaultFormLoginConfig()` adds this rule while the feature is enabled:

```php
['path' => '^/reset-password', 'roles' => ['PUBLIC_ACCESS']]
```

It comes before your own rules, so a catch-all `^/` rule requiring `ROLE_USER` does not lock
signed-out users out of the reset pages. If you build your `security` config without the
helper, add the rule yourself.

---

## Customising the pages

All three pages extend `@Ui/Layout/security.html.twig`, the layout of the login page, and are
built on the `Ui:AuthCard` component the login page uses. They look like the rest of the
signed-out screens without any work.

To change wording or markup, point a template setting at your own template, extend the
default and redefine a block:

```yaml
platform:
  security:
    reset_password:
      templates:
        request: 'security/reset_password/request.html.twig'
```

```twig
{# templates/security/reset_password/request.html.twig #}
{% extends '@SolidWorxPlatform/ResetPassword/request.html.twig' %}

{% block reset_request_description %}
    {{ 'Enter your work email and we will send you a reset link.'|trans }}
{% endblock %}
```

| Template | Context | Blocks |
|----------|---------|--------|
| `request` | `form`, `login_route` | `reset_request_description`, `reset_request_aside` |
| `check_email` | `reset_token`, `login_route` | `check_email_message`, `check_email_actions`, `check_email_aside` |
| `reset` | `form`, `password_requirements`, `login_route` | `reset_aside` |
| `email` | `user`, `resetUrl`, `expiresAtMessageKey`, `expiresAtMessageData` | none; replace it outright |

Render the expiry in your own email the way the default does:

```twig
{{ 'This link will expire in %count%.'|trans({'%count%': expiresAtMessageKey|trans(expiresAtMessageData, 'ResetPasswordBundle')}) }}
```

### The links

The **Forgot password?** link is the `Ui:ForgotPasswordLink` component. It renders nothing
while the feature is disabled, so you can put it in any password label of your own:

```twig
<label class="form-label">
    {{ 'Password'|trans }}
    <twig:Ui:ForgotPasswordLink />
</label>
```

Templates of your own can also check the `platform_reset_password_enabled` Twig global, and
link to the `_solidworx_platform_reset_password_request` route.

---

## See also

- [Authentication & Security](./index.md): the login firewall this builds on.
- [User Profile](./profile.md): the password rules, and the change-password page.
- [Two-Factor Authentication](./two-factor.md): still required after a reset, because the
  user signs in through the login form.
- [Configuration](../configuration/index.md): the full `platform.yaml` reference.
