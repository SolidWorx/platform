> **Note to AI Agents:** This file is your primary source of truth for working on the SolidWorx Platform project. It defines the architectural patterns, coding standards, and workflows you must strictly adhere to.

## 1. Project Overview

**SolidWorx Platform** is a modular, enterprise-grade application platform built on Symfony. It provides the foundational building blocks for SaaS applications, including Authentication, Multi-Factor Authentication (2FA), User Management, Subscription Management (SaaS), and a unified UI system.

### Core Technologies
- **Backend:** PHP 8.3+, Symfony 7.2+
- **Database:** Doctrine ORM
- **Frontend:** TypeScript, SCSS, Webpack Encore
- **UI Framework:** Tabler (based on Bootstrap 5)
- **Interactivity:** Hotwired Stimulus
- **Testing:** PHPUnit 12
- **Quality:** PHPStan (Max), Rector (PHP 8.4), ECS (PSR-12 + Symplify)

## 2. Architecture & Structure

The project is organized as a Monorepo of Symfony Bundles located in `src/Bundle/`.

### Bundle Responsibilities

1.  **PlatformBundle** (`src/Bundle/Platform/`)
    *   **Namespace:** `SolidWorx\Platform\PlatformBundle\`
    *   **Role:** Core infrastructure and shared logic.
    *   **Components:** Authentication, Security, 2FA (Scheb), Menu System, Form Types, Base Models, Core Twig Extensions.
    *   **Key Directories:** `Security/`, `Menu/`, `Model/`, `Controller/`.

2.  **SaasBundle** (`src/Bundle/Saas/`)
    *   **Namespace:** `SolidWorx\Platform\SaasBundle\`
    *   **Role:** Subscription and Billing logic.
    *   **Components:** Plans, Subscriptions, Webhooks (LemonSqueezy), Payment Integrations.
    *   **Key Directories:** `Entity/`, `Subscription/`, `Webhook/`, `Integration/`.

3.  **UiBundle** (`src/Bundle/Ui/`)
    *   **Namespace:** `SolidWorx\Platform\UiBundle\`
    *   **Role:** Visual presentation and frontend assets.
    *   **Components:** Layouts, Templates, UI Components, Frontend Assets.
    *   **Key Directories:** `templates/`, `assets/`, `Twig/`.

### Directory Map
- `src/Bundle/` - Source code for bundles.
- `src/Test/` - Shared test utilities and traits (`SolidWorx\Platform\Test\`).
- `tests/` - Application-level tests (`SolidWorx\Platform\Tests\`).
- `assets/` - Frontend source (JS/SCSS).
- `vendor/` - Composer dependencies.

## 3. Coding Standards & Quality

**You must strictly follow these standards. No exceptions.**

### PHP Standards
1.  **Strict Types:** Every PHP file **MUST** start with `declare(strict_types=1);`.
2.  **Header Comment:** Every PHP file **MUST** include the standard file header (see `ecs.php` for the template).
3.  **Modern PHP:** Use PHP 8.3+ features (Constructor Property Promotion, Readonly properties, Enums, Attributes).
4.  **Attributes:** Prefer PHP Attributes over Annotations or YAML/XML configuration (e.g., `#[Route]`, `#[Entity]`, `#[MenuBuilder]`).
5.  **Return Types:** All methods and functions **MUST** have declared return types (use `void` if nothing is returned).
6.  **Type Hinting:** Use strict type hinting for all arguments.

### Quality Tools
-   **ECS (EasyCodingStandard):** Enforces PSR-12 and Symplify rules.
    -   *Command:* `vendor/bin/ecs check --fix`
-   **PHPStan:** Static analysis at **Level Max**. Do not ignore errors; fix them.
    -   *Command:* `vendor/bin/phpstan`
-   **Rector:** Automated refactoring to PHP 8.4 standards.
    -   *Command:* `vendor/bin/rector`

### Testing
-   **Framework:** PHPUnit 12.
-   **Structure:** Tests should mirror the source directory structure in `tests/`.
-   **Requirement:** New features **MUST** include tests.
-   *Command:* `vendor/bin/phpunit`

## 4. Frontend Development

The frontend is decoupled from the backend logic but integrated via Webpack Encore.

-   **Location:** `assets/`
-   **Framework:** Stimulus.js for behavior, Tabler (Bootstrap 5) for styling.
-   **Controllers:** Located in `assets/controllers/`. Use lazy loading.
-   **Styles:** SCSS files in `assets/scss/`.
-   **Build:** Run `bun run build` in the `assets/` directory.

### Creating a New Stimulus Controller
1.  Create file in `assets/controllers/my_feature_controller.ts` (Prefer TypeScript controllers as much as possible). 
2.  Extend `Controller` from `@hotwired/stimulus`.
3.  Register is automatic if using Symfony UX, otherwise ensure it's picked up by `assets/controllers.json` or `package.json`.

## 5. Workflow for Agents

When asked to implement a feature or fix a bug:

1.  **Analyze:** Determine which Bundle the change belongs to.
    -   Core logic? -> `PlatformBundle`
    -   Billing/SaaS? -> `SaasBundle`
    -   UI/Visuals? -> `UiBundle`
2.  **Explore:** Read existing code in that bundle to understand patterns (e.g., how Menus are built, how Events are dispatched).
3.  **Implement:**
    -   Create classes with strict types and header comments.
    -   Use Dependency Injection (constructor injection).
    -   Use Attributes for configuration.
4.  **Verify:**
    -   Run `vendor/bin/ecs check --fix` to format code.
    -   Run `vendor/bin/rector` to modernize code.
    -   Run `vendor/bin/phpstan` to ensure no type errors.
    -   Run `vendor/bin/phpunit` to verify logic.

## 6. Common Commands Reference

### Setup & Maintenance
```bash
composer install
composer normalize --dry-run
```

### Quality Assurance
```bash
# 1. Code Style (Fix automatically)
vendor/bin/ecs check --fix

# 2. Refactoring (Apply automatically)
vendor/bin/rector

# 3. Static Analysis (Must pass)
vendor/bin/phpstan

# 4. Tests
vendor/bin/phpunit
```

### Frontend
```bash
cd assets
bun install
bun run build
```
