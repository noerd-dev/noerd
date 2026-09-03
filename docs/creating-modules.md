# Creating Modules

Using modules is completely optional. The application works perfectly fine without any modules.

The module approach is very inspired by https://github.com/InterNACHI/modular

Use the `noerd:make-module` Artisan command to create a new module with complete directory structure —
or choose **Module** in `php artisan noerd:make-app`, which asks the same questions, calls
`noerd:make-module` for you and runs the Composer and install steps (see [Create an App](make-app.md)).

## Quick Start

```bash
php artisan noerd:make-module
```

The command will ask for:
1. **Module name** (e.g., `inventory`)
2. **App title** (e.g., `Inventory`) and the **heroicon** of the tenant app

Every prompt has an option for scripted runs (`noerd:make-module inventory --title=Inventory --icon=cube`).

The scaffold contains **no model**: it is the module plumbing plus a dashboard. Every record type is
added afterwards with `noerd:make-resource` (see [Adding resources](#adding-resources)).


## Next Steps

After the command completes:

```bash
# 1. Register the module
composer update noerd/{module-name}

# 2. Install the app: copies the YAML configs into app-configs/{module}/,
#    registers the tenant app and runs the module's migrations
php artisan noerd:install-{module-name}

# 3. Add the first record type (model + migration first, see below)
php artisan noerd:make-resource Item --app={module-name}
```

Never register the app manually via `noerd:make-app` — the generated install command does all of
it and stays re-runnable.

## What the scaffold gives you

The generated module already follows every convention the shipped modules use — there is nothing to
"harden" before the first migrate:

- **Routes** (`routes/{module}-routes.php`): one group behind `['noerd', 'app-access:{module}']`
  (tenant must have the app assigned, see [Authentication](auth.md)) with the dashboard route
  `{module}`. `noerd:make-resource` appends the list and detail routes following the **naming
  convention** that route modals, `newRoute:` navigation entries and relation fields depend on —
  `{module}.{entities}` (list) and `{module}.{entity}.detail` (single record) — with namespaced
  component references (`{module}::{entities}-list`).
- **Dashboard**: `resources/views/components/{module}-dashboard.blade.php` (a `NoerdPage`), opened
  by the module's main route `{module}` and linked as the first navigation entry — every app ships
  its own dashboard, a module exactly like a root app.
- **Tenant app**: the app's `name` is the **UPPERCASE** module key (`INVENTORY`) — gates and test
  traits compare it exactly; `getAppRoute()` returns the module key so the app tile opens the
  dashboard route; `getAppIcon()` returns the chosen heroicon (`heroicon:outline:cube`). A module
  ships **no icon file** — only when no heroicon fits, add a Blade icon
  (`resources/views/components/icons/app.blade.php`) by hand and return `{module}::icons.app` instead.
- **Tenant-app migration**: `app-configs/stubs/add_{module}_tenant_app.php.stub` — the install
  command publishes it into the project's `database/migrations/`, so a deploy registers the app
  through `php artisan migrate` without the interactive install.
- **Composer**: `noerd/noerd` is required at the core version the module was scaffolded with
  (`composer.json` `require`), and `tests/` is autoloaded PSR-4 as `Noerd\{Module}\Tests\` so the
  module's own test traits (`tests/Traits/`) resolve without extra configuration. The command also
  adds `noerd/{module}` to the project's root `composer.json`.
- **Agent guidelines**: `resources/boost/guidelines/core.blade.php`, `AGENTS.md` and `CLAUDE.md`
  (see [AI Agents](ai-agents.md)).

Run `composer update noerd/{module-name}` again after editing the module's `composer.json` — a
plain `dump-autoload` does not refresh the package metadata Composer cached at install time.

## Adding resources

A module gets its record types one by one, exactly like a root app — the only difference is where
the generators write. Create the model and its migration inside the module, then run the generator
with the module as the app:

```bash
# 1. Model + migration in the module (namespace Noerd\{Module}\Models, table prefixed with the module key)
#    src/Models/Item.php — $guarded = [], BelongsToTenant, protected $table = 'inventory_items'
#    database/migrations/…_create_inventory_items_table.php — tenant_id + custom_attributes (JSON, nullable)
php artisan migrate

# 2. List + detail (Blade, YAML, routes, navigation)
php artisan noerd:make-resource Item --app=inventory
```

When the selected app is a module (`app-modules/{app}/composer.json` exists), `noerd:make-resource`,
`noerd:make-list`, `noerd:make-detail`, `noerd:make-page` and `noerd:make-dashboard` write into the
module: Blade components into `resources/views/components/` (referenced with the `{module}::`
Livewire namespace), routes into `routes/{module}-routes.php`, and every YAML and navigation entry
into **both** copies — the module template under `app-modules/{module}/app-configs/{module}/` and
the installed project copy under `app-configs/{module}/`. Table names are prefixed with the module
key (`inventory_items`) so two modules never collide; every table carries `tenant_id` (the model
uses `BelongsToTenant`) and a nullable `custom_attributes` JSON column cast to `array` (see
[Custom Attributes](#custom-attributes)).

## Install and update commands (required)

Every module that is a tenant app (has `app-configs/{module}/` with a `navigation.yml`) ships two
Artisan commands; `noerd:make-module` generates both from its stubs:

- **`noerd:install-{module}`** — extends `Illuminate\Console\Command`, uses the
  `HasModuleInstallation` and `RequiresNoerdInstallation` traits and implements `getModuleName()`,
  `getModuleKey()`, `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()` and `getSourceDir()`.
  Its `handle()` calls `$this->runModuleInstallation()`, which copies the YAML configs into
  `app-configs/{module}/`, registers the tenant app and runs the migrations. With `--scaffold`
  (declared by the generated command) it runs silently right after `noerd:make-app` — configs,
  registration and the tenant assignment question only, no migration or build prompt.
- **`noerd:update-{module}`** — a slim subclass of the install command whose `handle()` calls
  `$this->runModuleUpdate()` (never `runModuleInstallation()`, which prompts for the tenant
  assignment) plus the module's idempotent post-install steps. `noerd:update-all` discovers every
  command named `noerd:update-{module}` — a module without one silently drops out of the
  project-wide update.

Register both in the module's ServiceProvider inside
`if ($this->app->runningInConsole()) { $this->commands([...]); }`.
See [Reusable Traits](traits.md) for the two traits and [Artisan Commands](artisan-commands.md)
for `noerd:update-all`.

## Customization

After creation, customize the module:

- **Add a record type**: model + migration in the module, then `noerd:make-resource {Model} --app={module}`
- **Add fields**: Edit `details/{model}-detail.yml` (both copies)
- **Add columns**: Edit `lists/{models}-list.yml` (both copies)
- **Add migrations**: Create in `database/migrations/`
- **Add relationships**: Edit model in `src/Models/`
- **Add routes**: Edit `routes/{module-name}-routes.php`

## Custom Attributes

Modules are used across multiple projects. Some projects need project-specific fields that do not belong in the module itself. For this purpose, models support a `custom_attributes` JSON column — give every module model one from the start.

**Important:** Never modify module code or YAML files for project-specific fields. Use `custom_attributes` instead.

### Adding `custom_attributes` to a model that lacks it

1. Create a migration in the **project root** `database/migrations/`:

```php
Schema::table('your_table', function (Blueprint $table) {
    $table->json('custom_attributes')->nullable();
});
```

2. Add the cast to the model (in the module):

```php
protected function casts(): array
{
    return [
        'custom_attributes' => 'array',
    ];
}
```

### Usage

```php
// In PHP
$model->custom_attributes['my_key'];

// In Blade/Livewire detail views
$this->detailData['custom_attributes']['my_key'];
```

## Module Structure Reference

| Directory / file | Purpose |
|-----------|---------|
| `app-configs/{module}/` | YAML configuration templates (`lists/`, `details/`, `pages/`, `navigation.yml`) — copied into the project by the install command; the generators write both copies, keep them in sync |
| `app-configs/stubs/add_{module}_tenant_app.php.stub` | The tenant-app migration published by the install command |
| `database/migrations/`, `database/factories/`, `database/seeders/` | Database migrations, factories and seeders (module-owned) |
| `resources/boost/guidelines/core.blade.php` | Module-specific rules for AI coding agents, rendered by Laravel Boost (see [AI Agents](ai-agents.md)) |
| `skills/{name}/SKILL.md` | Claude Code skills shipped with the module — **top-level**, next to `src/` (the install/update command publishes every subfolder into the project's `.claude/skills/`). Only the noerd package itself keeps its skills in `resources/boost/skills/` |
| `resources/lang/de.json` | Translations (English key → German) |
| `resources/views/components/` | Livewire single-file components (`{module}-dashboard.blade.php`, `*-list.blade.php`, `*-detail.blade.php`, `*-page.blade.php`, `*-modal.blade.php`) — flat, no subfolders |
| `routes/{module}-routes.php` | Route definitions |
| `src/Commands/` | `{Module}InstallCommand`, `{Module}UpdateCommand` |
| `src/Models/` | Eloquent models (`$guarded`, `BelongsToTenant`) |
| `src/Providers/` | ServiceProvider |
| `tests/` | Pest tests, `tests/Traits/` for module test traits (see [Testing](testing.md)) |
| `AGENTS.md`, `CLAUDE.md` | Contributor notes for humans and AI agents working on the module |

## Next Steps

- [List View](list-view.md) - Customize list views
- [Detail View](detail-view.md) - Customize detail forms
- [Field Types](field-types.md) - Full YAML field reference
- [Testing](testing.md) - Testing module components
- [AI Agents](ai-agents.md) - Boost guidelines and skills shipped with noerd and your module
