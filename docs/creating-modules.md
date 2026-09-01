# Creating Modules

Using modules is completely optional. The application works perfectly fine without any modules. 

The module approach is very inspired by https://github.com/InterNACHI/modular

Use the `noerd:module` Artisan command to create a new module with complete directory structure.

## Quick Start

```bash
php artisan noerd:module
```

The command will ask for:
1. **Module name** (e.g., `inventory`)
2. **Main model name** (e.g., `item`)


## Next Steps

After the command completes:

```bash
# 1. Register the module
composer update noerd/{module-name}

# 2. Install the app: copies the YAML configs into app-configs/{module}/,
#    registers the tenant app and runs the module's migrations
php artisan noerd:install-{module-name}
```

Never register the app manually via `noerd:create-app` — the generated install command does all of
it and stays re-runnable.

## What the scaffold gives you

The generated module already follows every convention the shipped modules use — there is nothing to
"harden" before the first migrate:

- **Routes** (`routes/{module}-routes.php`): one group behind `['noerd', 'app-access:{module}']`
  (tenant must have the app assigned, see [Authentication](auth.md)), namespaced component
  references and the route **naming convention** that route modals, `newRoute:` navigation entries
  and relation fields depend on — `{module}` (dashboard), `{module}.{entities}` (list) and
  `{module}.{entity}.detail` (single record). The list component declares the record route as the
  preferred target and keeps the component as the fallback (`$detailRoute` + `$detailComponent`).
- **Table name**: prefixed with the module key (`inventory_items`, like `crm_contracts`,
  `hr_employees`) so two modules can never collide; the model sets `$table` explicitly. Every
  table carries `tenant_id` and a nullable `custom_attributes` JSON column (see
  [Custom Attributes](#custom-attributes)).
- **Tenant app**: the app's `name` is the **UPPERCASE** module key (`INVENTORY`) — gates and test
  traits compare it exactly; `getAppRoute()` returns the module key so the app tile opens the
  dashboard route; the app icon lives in `resources/views/components/icons/app.blade.php`.
- **Composer**: `noerd/noerd` is required at the core version the module was scaffolded with, and
  the `Noerd\{Module}\Tests\` namespace autoloads the module's own test traits.

Adapt the generated model, migration and YAML to your domain and run
`composer update noerd/{module-name}` again after editing the module's `composer.json` — a plain
`dump-autoload` does not refresh the package metadata Composer cached at install time.

## Install and update commands (required)

Every module that is a tenant app (has `app-configs/{module}/` with a `navigation.yml`) ships two
Artisan commands; `noerd:module` generates both from its stubs:

- **`noerd:install-{module}`** — extends `Illuminate\Console\Command`, uses the
  `HasModuleInstallation` and `RequiresNoerdInstallation` traits and implements `getModuleName()`,
  `getModuleKey()`, `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()` and `getSourceDir()`.
  Its `handle()` calls `$this->runModuleInstallation()`, which copies the YAML configs into
  `app-configs/{module}/`, registers the tenant app and runs the migrations.
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

- **Add fields**: Edit `details/{model}-detail.yml`
- **Add columns**: Edit `lists/{models}-list.yml`
- **Add migrations**: Create in `database/migrations/`
- **Add relationships**: Edit model in `src/Models/`
- **Add routes**: Edit `routes/{module-name}-routes.php`

## Custom Attributes

Modules are used across multiple projects. Some projects need project-specific fields that do not belong in the module itself. For this purpose, models support a `custom_attributes` JSON column.

**Important:** Never modify module code or YAML files for project-specific fields. Use `custom_attributes` instead.

### Adding `custom_attributes` to a model

1. Create a migration in the **project root** `database/migrations/`:

```php
Schema::table('your_table', function (Blueprint $table) {
    $table->json('custom_attributes')->nullable();
});
```

2. Add the cast to the model:

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
| `app-configs/{module}/` | YAML configuration templates (`lists/`, `details/`, `pages/`, `navigation.yml`) — copied into the project by the install command; keep both copies in sync |
| `database/migrations/`, `database/factories/`, `database/seeders/` | Database migrations, factories and seeders (module-owned) |
| `resources/boost/guidelines/core.blade.php` | Module-specific rules for AI coding agents, rendered by Laravel Boost (see [AI Agents](ai-agents.md)) |
| `resources/lang/de.json` | Translations (English key → German) |
| `resources/views/components/` | Livewire single-file components (`*-list.blade.php`, `*-detail.blade.php`, `*-page.blade.php`, `*-modal.blade.php`) — flat, no subfolders |
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
