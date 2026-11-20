---
title: "Plan Feature System"
description: "Feature flags and usage limits per subscription plan: define them in platform.yaml, override them per plan, and check them in PHP, Twig or the security voter."
---

The Plan Feature System provides a flexible way to manage feature flags and usage limits for subscription plans in your SaaS application. It supports boolean flags, numeric limits (with unlimited option), string values, and arrays.

## Overview

The Plan Feature System allows you to:

- Define features with default values in configuration
- Override feature values per subscription plan in the database
- Check if a subscriber has access to a feature
- Enforce usage limits (e.g., max users, max projects)
- Support unlimited values with `-1`
- Integrate with Symfony's security voter system
- Query which plans have specific features for upgrade prompts

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  FeatureGate (PlatformBundle)  ·  Twig feature_* functions       │
├─────────────────────────────────────────────────────────────────┤
│  PlanFeatureGate  │ PlanFeatureVoter │ PlanFeatureToggle         │
├─────────────────────────────────────────────────────────────────┤
│                     PlanFeatureManager                           │
├─────────────────────────────────────────────────────────────────┤
│              FeatureConfigRegistry (Config Defaults)             │
├─────────────────────────────────────────────────────────────────┤
│              PlanFeatureRepository (DB Overrides)                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Configuration

Define your features in your application's configuration file:

Features are defined in the `saas` section of `platform.yaml`. `saas` is a **root-level key**,
a sibling of `platform:`, not nested inside it. The section only applies when
`SolidWorxPlatformSaasBundle` is registered in `config/bundles.php`, and it also needs your
subscription entity. See [Configuration](../configuration/index.md#saas-saas) for those keys.

```yaml
# platform.yaml
platform:
  name: 'My App'

saas:
    doctrine:
        subscriptions:
            entity: App\Entity\Subscription
    features:
        # Boolean feature - simple on/off toggle
        api_access:
            type: boolean
            default: false
            description: "Enable API access for the plan"

        # Integer feature - numeric limit
        max_users:
            type: integer
            default: 5
            description: "Maximum number of team members"

        max_projects:
            type: integer
            default: 10
            description: "Maximum number of projects"

        storage_gb:
            type: integer
            default: 5
            description: "Storage limit in gigabytes"

        # String feature - configuration value
        support_level:
            type: string
            default: "email"
            description: "Support level (email, chat, phone, dedicated)"

        # Array feature - list of allowed values
        integrations:
            type: array
            default: []
            description: "Available third-party integrations"
```

### Feature Types

| Type | PHP Type | Description | Example Values |
|------|----------|-------------|----------------|
| `boolean` | `bool` | Simple on/off toggle | `true`, `false` |
| `integer` | `int` | Numeric limit or count | `5`, `100`, `-1` (unlimited) |
| `string` | `string` | Text value | `"basic"`, `"premium"` |
| `array` | `array` | List of values | `["slack", "jira"]` |

### Unlimited Values

For integer features, use `-1` to represent unlimited:

```php
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;

// Check if a feature is unlimited
if ($feature->isUnlimited()) {
    // No limit applies
}

// The constant is available
$unlimited = FeatureValue::UNLIMITED; // -1
```

---

## Core Concepts

### FeatureValue

The `FeatureValue` class is an immutable value object representing a resolved feature:

```php
use SolidWorx\Platform\PlatformBundle\Feature\FeatureValue;

// Properties (readonly)
$feature->key;    // string - feature identifier
$feature->type;   // FeatureType enum
$feature->value;  // int|bool|string|array - the actual value

// Methods
$feature->isUnlimited();           // true if value is -1 for integers
$feature->isEnabled();             // true if feature is "on"
$feature->allows(int $usage);      // true if usage is within limit
$feature->getRemainingQuota(int $usage); // remaining quota or null

// Type conversion helpers
$feature->asInt();     // int
$feature->asBool();    // bool
$feature->asString();  // string
$feature->asArray();   // array
```

### FeatureType Enum

```php
use SolidWorx\Platform\PlatformBundle\Feature\FeatureType;

FeatureType::BOOLEAN;  // 'boolean'
FeatureType::INTEGER;  // 'integer'
FeatureType::STRING;   // 'string'
FeatureType::ARRAY;    // 'array'
```

### isEnabled() Behavior

The `isEnabled()` method returns `true` based on the feature type:

| Type | Enabled When |
|------|--------------|
| `boolean` | Value is `true` |
| `integer` | Value is not `0` |
| `string` | Value is not empty string |
| `array` | Value is not empty array |

---

## Checking features in application code

Depend on `SolidWorx\Platform\PlatformBundle\Feature\FeatureGate` in your services and
controllers, not on the SaaS classes. The interface lives in the PlatformBundle, so the same code
runs whether the SaaS bundle is installed or not:

| SaaS bundle | `FeatureGate` service | Result |
|-------------|----------------------|--------|
| Registered | `PlanFeatureGate` | Values from the subscriber's plan, or the configured default |
| Not registered | `NoopFeatureGate` | Every feature enabled and unlimited; no upgrade options |

```php
use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;

final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly FeatureGate $features,
    ) {}

    public function create(#[CurrentUser] User $user): Response
    {
        $count = $this->projects->countFor($user);

        if (! $this->features->canUse('max_projects', $count, $user)) {
            $upgrade = $this->features->upgradeOptions('max_projects');
            // $upgrade->plans is a list of PlanReference (id, name); isEmpty() when none
        }

        $remaining = $this->features->remaining('max_projects', $count, $user); // null when unlimited
        $value = $this->features->resolve('support_level', $user)->asString();
        // ...
    }
}
```

| Method | Returns |
|--------|---------|
| `resolve(string $key, ?SubscribableInterface $for = null)` | `FeatureValue` |
| `isEnabled(string $key, ?SubscribableInterface $for = null)` | `bool` |
| `canUse(string $key, int $currentUsage = 0, ?SubscribableInterface $for = null)` | `bool` |
| `remaining(string $key, int $currentUsage = 0, ?SubscribableInterface $for = null)` | `?int` (null when unlimited or not a number) |
| `upgradeOptions(string $key)` | `UpgradeOptions` (plans that have the feature) |

### Leaving out the subscriber

When `$for` is `null`, the gate asks the `SubscriberResolver` service for the current subscriber.
The default resolver, `NullSubscriberResolver`, always returns `null`, so the gate falls back to
the **configured default**. Either always pass the subscriber, or alias your own resolver:

```php
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SubscriberResolver::class)]
final readonly class CurrentUserSubscriberResolver implements SubscriberResolver
{
    public function __construct(private Security $security) {}

    public function resolve(): ?SubscribableInterface
    {
        $user = $this->security->getUser();

        return $user instanceof SubscribableInterface ? $user : null;
    }
}
```

The rest of this page covers the SaaS-side services, which you need for managing plans and
for checks against a specific `Plan`.

---

## Basic Usage

### Inject the PlanFeatureManager

```php
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;

class ProjectController extends AbstractController
{
    public function __construct(
        private readonly PlanFeatureManager $featureManager,
    ) {}
}
```

### Check Features for a Plan

```php
use SolidWorx\Platform\SaasBundle\Entity\Plan;

public function createProject(Plan $plan): Response
{
    // Check if a boolean feature is enabled
    if (!$this->featureManager->hasFeature($plan, 'api_access')) {
        throw $this->createAccessDeniedException('API access not available');
    }

    // Get the full feature value
    $feature = $this->featureManager->getFeature($plan, 'max_projects');
    $limit = $feature->asInt();

    // Check usage against limit
    $currentProjects = $this->projectRepository->countByPlan($plan);
    if (!$this->featureManager->canUse($plan, 'max_projects', $currentProjects)) {
        throw new ProjectLimitExceededException($limit);
    }

    // Create project...
}
```

### Get All Features for a Plan

```php
// Returns array<string, FeatureValue>
$features = $this->featureManager->getAllFeatures($plan);

foreach ($features as $key => $feature) {
    echo "{$key}: {$feature->asString()}";
}
```

### Get Available Feature Configurations

```php
// Returns array<string, FeatureConfig>
$configs = $this->featureManager->getAvailableFeatures();

foreach ($configs as $key => $config) {
    echo "{$key}: {$config->description} (default: {$config->defaultValue})";
}
```

---

## Checking Features for Subscribers

The most common use case is checking features for the currently logged-in user or any entity implementing `SubscribableInterface`.

### Implement SubscribableInterface

Your User entity (or Company, Organization, etc.) must implement `SubscribableInterface`:

```php
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;

class User implements SubscribableInterface
{
    // The interface is a marker - no methods required
    // The system uses the Subscription entity to link subscribers to plans
}
```

### Check Features for a Subscriber

```php
public function dashboard(#[CurrentUser] User $user): Response
{
    // Check if subscriber has a feature
    if ($this->featureManager->hasFeatureForSubscriber($user, 'api_access')) {
        // Show API section
    }

    // Get feature value for subscriber
    $maxUsers = $this->featureManager->getFeatureForSubscriber($user, 'max_users');

    // Check usage for subscriber
    $currentUsers = $this->userRepository->countByTeam($user->getTeam());
    if (!$this->featureManager->canUseForSubscriber($user, 'max_users', $currentUsers)) {
        // Show upgrade prompt
    }
}
```

### When No Subscription Exists

If a subscriber doesn't have an active subscription, the system returns the default value from configuration:

```php
// User without subscription gets config defaults
$feature = $this->featureManager->getFeatureForSubscriber($newUser, 'max_users');
// Returns FeatureValue with default value (e.g., 5)
```

---

## Plan-Specific Overrides

While configuration defines defaults, you can override feature values for specific plans in the database.

### Setting Feature Overrides

```php
// Set a custom value for a specific plan
$this->featureManager->setFeature($premiumPlan, 'max_users', 100);
$this->featureManager->setFeature($premiumPlan, 'api_access', true);
$this->featureManager->setFeature($enterprisePlan, 'max_users', -1); // Unlimited

// Remove an override (revert to config default)
$this->featureManager->removeFeature($plan, 'max_users');
```

### Feature Resolution Order

1. Check for plan-specific override in database (`PlanFeature` entity)
2. Fall back to configuration default

### Find Plans with a Feature

Useful for upgrade prompts:

```php
// Find all plans that have API access enabled
$plansWithApi = $this->featureManager->findPlansWithFeature('api_access');

// Exclude the current plan
$upgradePlans = $this->featureManager->findPlansWithFeature(
    'api_access',
    excludePlan: $currentPlan
);

// Check if feature is available on any plan
if ($this->featureManager->isFeatureAvailableOnAnyPlan('advanced_analytics')) {
    // Show "Upgrade to access this feature" message
}
```

---

## Symfony Security Voter

The `PlanFeatureVoter` integrates with Symfony's security system for authorization. It votes on
any attribute starting with `FEATURE_`; the rest is lowercased to get the feature key, so
`FEATURE_API_ACCESS` and `FEATURE_api_access` both check `api_access`.

### Basic Usage

```php
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
    ) {}

    public function apiEndpoint(): Response
    {
        // Check feature for current user
        if (!$this->isGranted('FEATURE_api_access', $this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        // Or use the authorization checker
        if (!$this->authChecker->isGranted('FEATURE_api_access', $this->getUser())) {
            throw $this->createAccessDeniedException();
        }
    }
}
```

### With Usage Limits

For integer features, pass an array with the subscriber and current usage:

```php
$currentProjects = $this->projectRepository->countByUser($user);

if (!$this->isGranted('FEATURE_max_projects', [
    'subscriber' => $user,
    'usage' => $currentProjects,
])) {
    throw new ProjectLimitExceededException();
}
```

### Using Attributes

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProjectController extends AbstractController
{
    #[IsGranted('FEATURE_api_access', subject: 'user')]
    public function apiAction(#[CurrentUser] User $user): Response
    {
        // Only accessible if user's plan has api_access enabled
    }
}
```

### Voter Behavior

| Scenario | Result |
|----------|--------|
| Boolean feature enabled | `ACCESS_GRANTED` |
| Boolean feature disabled | `ACCESS_DENIED` |
| Integer within limit | `ACCESS_GRANTED` |
| Integer at/over limit | `ACCESS_DENIED` |
| Undefined feature | `ACCESS_DENIED` |
| No subscriber provided | `ACCESS_ABSTAIN` |

---

## Twig Integration

The PlatformBundle registers Twig functions backed by `FeatureGate`, so templates work with or
without the SaaS bundle (without it, every feature is enabled and unlimited). The subscriber is
the **last, optional** argument; leave it out and the [`SubscriberResolver`](#leaving-out-the-subscriber)
decides.

### Available Functions

| Function | Returns |
|----------|---------|
| `feature_enabled(key, for = null)` | `bool` — is the feature on |
| `feature_can_use(key, usage = 0, for = null)` | `bool` — is `usage` within the limit |
| `feature_remaining(key, usage = 0, for = null)` | `int` or `null` — quota left; `null` when unlimited |
| `feature_unlimited(key, for = null)` | `bool` — is the integer limit `-1` |
| `feature_upgrade(key)` | `UpgradeOptions` — plans that include the feature |

There is no function for the raw value; resolve it in the controller with
`FeatureGate::resolve()` and pass it to the template.

### Usage in Templates

```twig
{# Boolean feature #}
{% if feature_enabled('api_access', app.user) %}
    <a href="{{ path('api_docs') }}">API Documentation</a>
{% else %}
    {% set upgrade = feature_upgrade('api_access') %}
    {% if not upgrade.empty %}
        <p>Available on: {{ upgrade.plans|map(p => p.name)|join(', ') }}</p>
        <a href="{{ path('pricing') }}">View plans</a>
    {% endif %}
{% endif %}

{# Usage limit #}
{% set current_users = team.members|length %}

{% if feature_unlimited('max_users', app.user) %}
    <p>Unlimited team members.</p>
{% else %}
    <p>You can add {{ feature_remaining('max_users', current_users, app.user) }} more team members.</p>
{% endif %}

{% if not feature_can_use('max_users', current_users, app.user) %}
    <p>You've reached your team member limit.</p>
{% endif %}
```

The extension is `SolidWorx\Platform\PlatformBundle\Twig\Extension\FeatureExtension`, with the
implementations in the lazily loaded `SolidWorx\Platform\PlatformBundle\Twig\Runtime\FeatureRuntime`.

---

## Feature Toggle Interface

For integration with feature toggle libraries or custom implementations, use the `FeatureToggleInterface`:

```php
use SolidWorx\Platform\SaasBundle\Feature\FeatureToggleInterface;

class MyFeatureToggle implements FeatureToggleInterface
{
    public function isActive(string $featureKey, SubscribableInterface $subscriber): bool
    {
        // Custom logic
    }

    public function getValue(string $featureKey, SubscribableInterface $subscriber): int|bool|string|array
    {
        // Custom logic
    }

    public function hasFeature(string $featureKey): bool
    {
        // Custom logic
    }
}
```

### Default Implementation

The `PlanFeatureToggle` class provides the default implementation:

```php
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureToggle;

class MyService
{
    public function __construct(
        private readonly FeatureToggleInterface $featureToggle,
    ) {}

    public function doSomething(User $user): void
    {
        if ($this->featureToggle->isActive('premium_feature', $user)) {
            // Premium behavior
        }
    }
}
```

---

## API Reference

### PlanFeatureManager

The main service for all feature operations.

#### Plan-based Methods

```php
// Get a feature value for a plan (throws UndefinedFeatureException if not defined)
getFeature(Plan $plan, string $featureKey): FeatureValue

// Check if a boolean feature is enabled for a plan
hasFeature(Plan $plan, string $featureKey): bool

// Check if usage is within the limit for a plan
canUse(Plan $plan, string $featureKey, int $currentUsage = 0): bool

// Get all features for a plan
getAllFeatures(Plan $plan): array<string, FeatureValue>
```

#### Subscriber-based Methods

```php
// Get a feature value for a subscriber (uses their subscription's plan)
getFeatureForSubscriber(SubscribableInterface $subscriber, string $featureKey): FeatureValue

// Check if a boolean feature is enabled for a subscriber
hasFeatureForSubscriber(SubscribableInterface $subscriber, string $featureKey): bool

// Check if usage is within the limit for a subscriber
canUseForSubscriber(SubscribableInterface $subscriber, string $featureKey, int $currentUsage = 0): bool
```

#### Management Methods

```php
// Set a feature override for a plan
setFeature(Plan $plan, string $featureKey, int|bool|string|array $value): void

// Remove a feature override (revert to config default)
removeFeature(Plan $plan, string $featureKey): void

// Get all available feature configurations
getAvailableFeatures(): array<string, FeatureConfig>

// Find plans with a specific feature enabled
findPlansWithFeature(string $featureKey, ?Plan $excludePlan = null): array<Plan>

// Check if a feature is available on any plan
isFeatureAvailableOnAnyPlan(string $featureKey): bool

// The configured default, ignoring any plan override
getConfigDefault(string $featureKey): FeatureValue

// Clear the feature cache (Symfony ResetInterface)
reset(): void
```

### FeatureValue

Immutable value object for a resolved feature.

```php
// Properties
public readonly string $key;
public readonly FeatureType $type;
public readonly int|bool|string|array $value;

// Constants
public const int UNLIMITED = -1;

// Methods
isUnlimited(): bool              // True if type is INTEGER and value is -1
isEnabled(): bool                // True if feature is "on" (type-dependent)
allows(int $currentUsage): bool  // True if usage is within limit
getRemainingQuota(int $currentUsage): ?int  // Remaining quota or null

// Type conversion
asInt(): int
asBool(): bool
asString(): string
asArray(): array
```

### FeatureConfig

Configuration value object for a feature definition.

```php
// Properties
public readonly string $key;
public readonly FeatureType $type;
public readonly int|bool|string|array $defaultValue;
public readonly string $description;

// Methods
toFeatureValue(): FeatureValue  // Convert to FeatureValue
```

---

## Best Practices

### 1. Use Descriptive Feature Keys

```yaml
# Good
features:
    max_team_members:
        type: integer
        default: 5

# Avoid
features:
    mtm:
        type: integer
        default: 5
```

### 2. Always Provide Descriptions

```yaml
features:
    advanced_analytics:
        type: boolean
        default: false
        description: "Access to advanced analytics dashboard with custom reports"
```

### 3. Handle Undefined Features Gracefully

```php
// Use hasFeature for safe checking (returns false if undefined)
if ($this->featureManager->hasFeature($plan, 'maybe_exists')) {
    // Safe
}

// getFeature throws SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException if not defined
try {
    $feature = $this->featureManager->getFeature($plan, 'feature_key');
} catch (UndefinedFeatureException $e) {
    // Handle missing feature
}
```

### 4. Cache Appropriately

`PlanFeatureManager` caches each resolved plan feature in the autowired application cache
(`cache.app` unless you configure otherwise), so values survive between requests. After changing
overrides outside `setFeature()` / `removeFeature()`, for example straight in the database, clear it:

```php
$this->featureManager->reset();
```

`reset()` empties the whole pool when the cache is a Symfony adapter.

### 5. Use the Voter for Authorization

Prefer the security voter for access control:

```php
// Good - uses Symfony's security system
#[IsGranted('FEATURE_api_access', subject: 'user')]
public function apiAction(): Response {}

// Also good - explicit check
if (!$this->isGranted('FEATURE_api_access', $user)) {
    throw $this->createAccessDeniedException();
}
```

---

## Database Schema

The `PlanFeature` entity stores plan-specific overrides:

| Column | Type | Description |
|--------|------|-------------|
| id | ULID | Primary key |
| plan_id | ULID | Foreign key to Plan, deleted with the plan |
| feature_key | string(100) | Feature identifier |
| type | string(20) | Feature type (boolean, integer, string, array) |
| value | JSON | The override value |
| description | text, nullable | Feature description |

The table name comes from `saas.doctrine.db_schema.table_names.plan_feature` (default `saas_plan_feature`).

Unique constraint on `(plan_id, feature_key)` ensures one override per feature per plan.

---

## Troubleshooting

### Feature Returns Default Instead of Override

1. Verify the override exists in the database
2. Check that the plan ID matches
3. Clear the feature cache: `$featureManager->reset()`, or `php bin/console cache:pool:clear cache.app`

### UndefinedFeatureException

1. Ensure the feature is defined in configuration
2. Check for typos in the feature key
3. Verify the configuration file is being loaded

### Subscriber Returns Default Values

1. Verify the subscriber has an active subscription
2. Check that the subscription is linked to a plan
3. Ensure `SubscriptionManager::getSubscriptionFor()` returns the subscription
