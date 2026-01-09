# Architecture

This document describes the architecture and core concepts of the Noerd Framework.

## Overview

Noerd is a modular Laravel framework with the following core concepts:

1. **Multi-Tenant Architecture** - Multiple tenants with their own apps
2. **YAML-based Configuration** - UI definitions without PHP code
3. **Livewire Volt** - Single-file components
4. **Modular System** - Independent, reusable modules

## Directory Structure

```
app-modules/noerd/
├── app-contents/           # Default YAML configurations
│   └── setup/
│       ├── lists/          # List configurations
│       ├── details/        # Detail/form configurations
│       └── navigation.yml  # Navigation
├── database/
│   ├── migrations/         # Database migrations
│   ├── factories/          # Model factories for tests
│   └── seeders/            # Database seeders
├── public/
│   └── fonts/              # Font assets
├── resources/
│   ├── views/
│   │   ├── components/     # Blade components (90+)
│   │   └── livewire/       # Livewire Volt components
│   └── lang/
│       ├── de.json         # German translations
│       └── en.json         # English translations
├── routes/
│   └── noerd-routes.php    # Route definitions
├── src/
│   ├── Commands/           # Artisan commands
│   ├── Controllers/        # Controllers
│   ├── Helpers/            # Helper classes
│   ├── Middleware/         # HTTP middleware
│   ├── Models/             # Eloquent models
│   ├── Providers/          # ServiceProvider
│   ├── Services/           # Business logic
│   └── Traits/             # Reusable traits
├── stubs/                  # Code generation templates
└── tests/                  # Pest tests
```

## Multi-Tenant Architecture

### Data Model

```
User (1) ──────┬────── (*) Tenant
               │
               │       Tenant (1) ──── (*) TenantApp
               │
               └────── (*) UserRole
                              │
                              └────── (1) Profile
```

### User Model

The `User` model (`src/Models/User.php`) is the central user model:

```php
class User extends Authenticatable
{
    // Relationships
    public function tenants(): BelongsToMany
    public function adminTenants(): BelongsToMany
    public function roles(): BelongsToMany
    public function userSetting(): HasOne

    // Methods
    public function selectedTenant(): ?Tenant
    public function isAdmin(): bool
    public function isSuperAdmin(): bool
    public function initials(): string
}
```

**Important Attributes:**
- `selected_app` - Currently selected app (from UserSetting)
- `selected_tenant_id` - Currently selected tenant

### Tenant Model

The `Tenant` model represents a tenant:

```php
class Tenant extends Model
{
    public function users(): BelongsToMany
    public function tenantApps(): BelongsToMany
    public function profiles(): HasMany
    public function setting(): HasOne
}
```

### TenantApp Model

Apps/modules that can be assigned to a tenant:

```php
class TenantApp extends Model
{
    protected $fillable = [
        'title',      // Display name
        'name',       // Technical name (e.g., 'CMS')
        'icon',       // Icon reference
        'route',      // Start route
        'is_active',  // Active/Inactive
        'is_hidden',  // Hidden in menu
    ];
}
```

### Profile and UserRole

Profiles define permission levels:

| Profile | Description |
|---------|-------------|
| SUPERADMIN | Full access to all tenants |
| ADMIN | Admin rights for one tenant |
| USER | Standard user |

## ServiceProvider

The `NoerdServiceProvider` (`src/Providers/NoerdServiceProvider.php`) registers:

### Resources

```php
$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
$this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
$this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
$this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');
```

### Livewire Volt

```php
Volt::mount(__DIR__ . '/../../resources/views/livewire');
```

### Middleware

```php
$router->aliasMiddleware('setup', SetupMiddleware::class);
$router->pushMiddlewareToGroup('web', SetUserLocale::class);
```

### Blade Components

```php
Blade::component('app-layout', AppLayout::class);
```

### Configuration

```php
config(['livewire.layout' => 'noerd::components.layouts.app']);
```

## YAML Configuration System

The framework uses YAML files for UI definitions.

### Search Path (StaticConfigHelper)

The `findConfigPath()` method searches in this order:

1. `app-configs/{current_app}/{subPath}` - Project-specific
2. `app-configs/{other_allowed_apps}/{subPath}` - Fallback to other apps
3. `app-modules/{module}/app-contents/{app-key}/{subPath}` - Module sources
4. `app-modules/{other_modules}/app-contents/{app-key}/{subPath}` - Other modules

### Configuration Types

| Type | Path | Description |
|------|------|-------------|
| Lists | `lists/*.yml` | Table configurations |
| Details | `details/*.yml` | Form configurations |
| Navigation | `navigation.yml` | Menu structure |

## Livewire Volt Integration

### Component Structure

```php
<?php
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'users-list';

    public function with(): array
    {
        return [
            'rows' => User::paginate(50),
            'tableConfig' => StaticConfigHelper::getTableConfig('users-list'),
        ];
    }
} ?>

<div>
    {{-- Blade Template --}}
</div>
```

### Noerd Trait

The `Noerd` trait (`src/Traits/Noerd.php`) provides:

- Pagination (`WithPagination`)
- Sorting (`sortBy()`, `$sortField`, `$sortAsc`)
- Search (`$search`)
- Modal management (`mountModalProcess()`, `closeModalProcess()`)
- Tab support (`$currentTab`)
- Validation (`validateFromLayout()`)

## Middleware

### SetupMiddleware

Checks if setup is complete:
- Admin user exists
- At least one TenantApp exists
- Redirects to `/setup` if not

### SetUserLocale

Sets application locale based on user settings:

```php
App::setLocale($user->userSetting?->locale ?? 'en');
```

## Translations

Translations are stored as JSON files:

```
resources/lang/
├── de.json
└── en.json
```

### Key Conventions

| Pattern | Example | Usage |
|---------|---------|-------|
| `noerd_nav_*` | `noerd_nav_users` | Navigation |
| `noerd_label_*` | `noerd_label_email` | Field labels |
| `noerd_status_*` | `noerd_status_active` | Status displays |

### Usage

In Blade:
```blade
{{ __('noerd_label_email') }}
```

In YAML (without namespace):
```yaml
label: noerd_label_email
```

## Events and Listeners

### Livewire Events

| Event | Description |
|-------|-------------|
| `reloadTable-{COMPONENT}` | Reload table |
| `close-modal-{COMPONENT}` | Close modal |
| `downModal2` | Modal system event |

## Next Steps

- [Components](components.md) - Use UI components
- [YAML Configuration](yaml-configuration.md) - Create configurations
- [Creating Modules](creating-modules.md) - Develop your own modules
