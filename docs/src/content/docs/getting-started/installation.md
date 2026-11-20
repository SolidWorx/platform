---
title: Installation
description: Install SolidWorx Platform into a Symfony application — requirements, Composer and npm packages, the kernel and the bundles.
---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4 or newer |
| Symfony | 8.1 or newer |
| Doctrine ORM | 2.15 or 3.x |
| Node.js (frontend assets) | 18 or newer |

## 1. Install the Composer package

```bash
composer require solidworx/platform
```

The package contains three bundles:

| Bundle | Class | Provides |
|--------|-------|----------|
| Platform | `SolidWorx\Platform\PlatformBundle\SolidWorxPlatformBundle` | Authentication, 2FA, profile, multi-tenancy, form and Doctrine types |
| UI | `SolidWorx\Platform\UiBundle\SolidWorxPlatformUiBundle` | Tabler layouts, Twig components, frontend assets |
| SaaS | `SolidWorx\Platform\SaasBundle\SolidWorxPlatformSaasBundle` | Plans, subscriptions, feature limits, payment webhooks |

## 2. Extend the platform kernel

The platform kernel reads `platform.yaml`, registers the Platform bundle plus the bundles your
enabled features need (two-factor, password reset, email verification), and imports the login
routes.

```php
// src/Kernel.php
namespace App;

use SolidWorx\Platform\PlatformBundle\Kernel as PlatformKernel;

final class Kernel extends PlatformKernel
{
}
```

## 3. Register the bundles you use

The kernel registers the Platform bundle for you. Add the UI bundle — and the SaaS bundle if you
sell subscriptions — to `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    SolidWorx\Platform\UiBundle\SolidWorxPlatformUiBundle::class => ['all' => true],
    // Only when you sell subscriptions: it needs entities of its own, see the SaaS docs.
    // SolidWorx\Platform\SaasBundle\SolidWorxPlatformSaasBundle::class => ['all' => true],
];
```

## 4. Create `platform.yaml`

Create `platform.yaml` next to `composer.json`. The schema comment gives you autocompletion and
validation in VS Code and PhpStorm:

```yaml
# platform.yaml
# yaml-language-server: $schema=./vendor/solidworx/platform/platform-schema.json
platform:
  name: 'My App'
```

Every option has a default. See the [configuration reference](../configuration/index.md).

## 5. Install the frontend package

```bash
bun add @solidworx/platform   # or: npm install @solidworx/platform
```

Then build on the platform's Encore config in `webpack.config.js`:

```js
import Encore from '@solidworx/platform/webpack.config.js';

export default Encore
    .enableStimulusBridge('./assets/controllers.json')
    .getWebpackConfig();
```

[Frontend assets](../frontend/index.md) covers the entries, Stimulus controllers and theming.

## Next steps

- [Quick start](./quick-start.md) — add a user entity and the login firewall.
