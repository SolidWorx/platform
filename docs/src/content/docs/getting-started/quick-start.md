---
title: Quick start
description: From a fresh Symfony app with the platform installed to a working, styled login in five steps.
---

This page assumes you have finished [Installation](./installation.md).

## 1. Add a user entity

Extend the platform's user model. It already implements Symfony's `UserInterface`,
`PasswordAuthenticatedUserInterface` and the two-factor contract.

```php
// src/Entity/User.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Model\User as PlatformUser;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends PlatformUser
{
}
```

Point the platform at it:

```yaml
# platform.yaml
platform:
  name: 'My App'
  models:
    user: App\Entity\User
```

## 2. Configure security in one call

Replace `config/packages/security.yaml` with `security.php`:

```php
// config/packages/security.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return App::config(LoginExtension::defaultFormLoginConfig([
    'access_control' => [
        ['path' => '^/login', 'roles' => ['PUBLIC_ACCESS']],
        ['path' => '^/', 'roles' => ['ROLE_USER']],
    ],
]));
```

This gives you form login at `/login`, logout, remember-me, throttling and password hashing.
The login page and its route ship with the platform — you do not write a controller.
[Authentication & Security](../security/index.md) explains every part and how to override it.

## 3. Create the database schema

```bash
php bin/console doctrine:schema:update --force
```

Use `doctrine:migrations:diff` and `doctrine:migrations:migrate` instead if your app uses
DoctrineMigrationsBundle.

## 4. Build the assets

```bash
bun run build   # or: npx encore production
```

## 5. Sign in

Start the server (`symfony serve`, or `php -S localhost:8000 -t public`) and open `/login`.

:::caution
New users are created **disabled** (`enabled = false`). Enable a user before signing in with it.
:::

## Turn on more features

Each of these is a `platform.yaml` switch:

| Feature | Setting | Guide |
|---------|---------|-------|
| Two-factor authentication | `platform.security.two_factor.enabled: true` | [Two-factor](../security/two-factor.md) |
| Forgot password | `platform.security.reset_password.enabled: true` | [Password reset](../security/reset-password.md) |
| Multi-tenancy | see the guide | [Multi-tenancy](../multi-tenancy/index.md) |
| Subscriptions | `saas:` section | [Plan features](../saas/plan-features.md) |
