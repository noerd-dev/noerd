# Artisan Commands

The Noerd Framework provides several Artisan commands.

## Overview

### Core Commands

| Command | Description |
|---------|-------------|
| `noerd:install` | Install framework |
| `noerd:demo` | Install demo data (model, migration, YAML config, navigation) |
| `noerd:update` | Update noerd content files without running installation setup |
| `noerd:create-admin` | Create admin user |
| `noerd:make-admin` | Make user admin |
| `noerd:create-tenant` | Create new tenant |
| `noerd:create-app` | Create new TenantApp |
| `noerd:assign-apps-to-tenant` | Assign apps to tenant |
| `noerd:module` | Create new module with complete structure |
| `noerd:make-resource` | Generate list/detail Blade and YAML files from an existing Eloquent model |
| `noerd:make-collection` | Create setup collection |

## noerd:install

Installs the Noerd Framework and performs basic configuration.

```bash
php artisan noerd:install
```

## noerd:demo

Installs demo data into your project. This publishes a fully working Demo Customers app with model, migration, Blade components, YAML configuration, navigation, and routes. The demo app is automatically registered as a TenantApp and assigned to all tenants.

```bash
php artisan noerd:demo
```

This command is also offered during `noerd:install`. It can be run independently at any time.

## noerd:update

Updates noerd content files without running the full installation setup.

```bash
php artisan noerd:update
```

## noerd:create-admin

Creates a new administrator user.

```bash
php artisan noerd:create-admin
```

## noerd:make-admin

Makes an existing user an administrator.

```bash
php artisan noerd:make-admin {userId}
```

## noerd:create-tenant

Creates a new tenant.

```bash
php artisan noerd:create-tenant
```


## noerd:create-app

Creates a new TenantApp.

```bash
php artisan noerd:create-app
```

## noerd:assign-apps-to-tenant

Assigns apps to a tenant.

```bash
php artisan noerd:assign-apps-to-tenant
```

## noerd:module

Creates a new module with complete directory structure, including model, migration, Livewire components, YAML configurations, and translations.

```bash
php artisan noerd:module
# or with module name
php artisan noerd:module inventory
```

## noerd:make-resource

Generates list and detail Blade components along with their YAML configuration files from an existing Eloquent model. The command reads the model's database columns and automatically maps them to appropriate YAML field types.

```bash
# With full namespace
php artisan noerd:make-resource "App\Models\Invoice"

# Short name (resolves to App\Models\Invoice)
php artisan noerd:make-resource Invoice

# Module model
php artisan noerd:make-resource "Modules\Accounting\Models\BankAccount"
```

### What it generates

The command creates four files:

| File | Location |
|------|----------|
| List Blade | `resources/views/components/{entities}-list.blade.php` |
| Detail Blade | `resources/views/components/{entity}-detail.blade.php` |
| List YAML | `app-configs/{app}/lists/{entities}-list.yml` |
| Detail YAML | `app-configs/{app}/details/{entity}-detail.yml` |

### Additional actions

- **Routes** — Appends list and detail routes to `routes/web.php`
- **Navigation** — Adds a navigation entry to `app-configs/{app}/navigation.yml`

### Interactive app selection

The command prompts you to select which app the resource belongs to from all active entries in `tenant_apps`. The module name is auto-detected from the model's namespace via `composer.json` autoload mappings.

### Database column type mapping

Columns `id`, `tenant_id`, `created_at`, `updated_at`, and `deleted_at` are excluded.

## noerd:make-collection

Creates a new setup collection.

```bash
php artisan noerd:make-collection
```