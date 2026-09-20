# Creating Modules

Modules are optional — an app can live in the project root just as well. A module is a Composer
package under `app-modules/{module}` (the approach is inspired by
[InterNACHI/modular](https://github.com/InterNACHI/modular)). Scaffold one with `noerd:make-module`,
or choose **Module** in `php artisan noerd:make-app`, which asks the same questions and runs the
Composer and install steps for you (see [Create an App](make-app.md)).

## Quick Start

```bash
php artisan noerd:make-module
```

The command will ask for:
1. **Module name** (e.g., `inventory`)
2. **App title** (e.g., `Inventory`) and the **heroicon** of the tenant app

Every prompt has an option for scripted runs (`noerd:make-module inventory --title=Inventory --icon=cube`).

The scaffold contains **no model**: it is the module plumbing plus a dashboard. Every record type is
added afterwards with `noerd:make-resource` (see [Adding resources](#adding-resources)). With the
first local module the command also prepares the host: it creates `app-modules/`, adds the
`app-modules/*` path repository to `composer.json` and the `app-modules` test suite to `phpunit.xml`
(both idempotent).

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
  traits compare it exactly; `getAppRoute()` returns the ROUTE NAME of the dashboard (the module
  key), so the app tile opens it; `getAppIcon()` returns the chosen heroicon (`heroicon:outline:cube`). A module
  ships **no icon file** — only when no heroicon fits, add a Blade icon
  (`resources/views/components/icons/app.blade.php`) by hand and return `{module}::icons.app` instead.
- **Tenant-app migration**: `app-configs/stubs/add_{module}_tenant_app.php.stub` — the install
  command publishes it into the project's `database/migrations/`, so a deploy registers the app
  through `php artisan migrate` without the interactive install. The update command publishes it
  too when the app is registered but the host has no such migration yet (from the row's current
  title, icon and route). This is the ONE way an app is registered: a module ships no registering
  migration of its own in `database/migrations/` — it would register the app in every project
  that merely has the package installed, and make the install command divert to its update path.
- **Composer**: `noerd/noerd` is required at the core version the module was scaffolded with
  (`composer.json` `require`). `Noerd\{Module}\Tests\` → `tests/` sits in the production
  `autoload` block, not in `autoload-dev`: Composer only dumps the dev autoload of the ROOT package,
  so a host would never find the module's test traits (`tests/Traits/`) otherwise. The command also
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

Every module ships two Artisan commands; `noerd:make-module` generates both from its stubs. What a
module publishes is DECLARED, never coded — no module carries its own `publishConfig()`, copy loop,
migration prompt or `composer require` call.

**A tenant app** (has `app-configs/{module}/` with a `navigation.yml`):

- **`noerd:install-{module}`** — extends `Illuminate\Console\Command`, uses the
  `HasModuleInstallation` trait and implements `getModuleName()`, `getModuleKey()`,
  `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()` and `getSourceDir()`. Its `handle()` is
  `return $this->runModuleInstallation();` — that copies the YAML configs into
  `app-configs/{module}/`, registers the tenant app, asks which tenants get it, offers
  `php artisan migrate`, runs the module's setup steps and offers the frontend build. With
  `--scaffold` (declared by the generated command) it runs silently right after `noerd:make-app` —
  configs, registration and the tenant assignment question only. On a project where the base
  package is not installed yet, the command runs `noerd:install` first and then continues —
  installing a module is a valid first command in a fresh project.
- **`noerd:update-{module}`** — a slim subclass of the install command whose `handle()` is
  `return $this->runModuleUpdate();`. It republishes, never migrates and never asks about tenants.
  `noerd:update-all` discovers every command named `noerd:update-{module}` — a module without one
  silently drops out of the project-wide update.

**A support module** (no tenant app, no navigation — payment, notifications, …) uses the
`InstallsNoerdModule` trait, implements only `getModuleName()` and calls
`runSupportModuleInstallation()` / `runSupportModuleUpdate()`. `HasModuleInstallation` builds on
the same trait, so everything below applies to both kinds.

| Declare | Effect |
|---------|--------|
| `getConfigFiles(): array` — `['inventory.php']`, or `['x.php' => 'stubs/x.php.stub']` | Published into the host's `config/`. Install: an existing file is kept unless the user agrees or `--force`. Update: an existing file is **never** overwritten (not even under `--force` — `noerd:update-all --force` must not reset a host's settings); missing top-level keys are reported |
| a `{module}/app-configs/setup/` folder (collections, lists, details) | Published into `app-configs/setup/`; existing files are kept unless `--force`. The navigation is never copied — use `ensureSetupNavigation()` |
| `getAppConfigsDir(): ?string` (support modules) | YAML of screens that hang in other apps' navigation, published into `app-configs/{folder}/` |
| `getAdditionalSubdirectories(): array` | Extra app-config folders beyond `lists`, `details`, `pages`, `settings` (the CMS ships `forms`) |
| `getRequiredModules(): array` | Modules installed first and assigned along — see below |
| `publishModuleExtras(bool $update): void` | Files beyond the declared ones — the auditing migration (`PublishesAuditMigration`), a patch to a host config file. Runs with the publishing steps, **before** the migration prompt, on install and update; idempotent, asks nothing |
| `ensureModuleSetup(): void` | The module's own steps: `ensureSetupNavigation()`, `ensureQuickMenuButton()`, `ensureDashboardWidget()`, seeds. Runs at the end of the installation (after the migration prompt) **and on every update** — it must be idempotent, ask nothing, and guard tables the user may not have migrated yet |

The Claude skills of a top-level `skills/` folder and the Boost registration (`boost.json`) are
handled by both flows without any declaration.

```php
class InventoryInstallCommand extends Command
{
    use HasModuleInstallation;

    protected $signature = 'noerd:install-inventory {--force} {--migrate} {--build} {--scaffold}';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getConfigFiles(): array
    {
        return ['inventory.php'];
    }

    protected function ensureModuleSetup(): void
    {
        $this->ensureQuickMenuButton(['apps' => ['INVENTORY'], 'component' => 'inventory::quick-menu.low-stock']);
    }

    // getModuleName(), getModuleKey(), getDefaultAppTitle(), getAppIcon(), getAppRoute(), getSourceDir()
}
```

Nothing migrates or builds implicitly: in a non-interactive run (CI, deploy) the migration and the
build are skipped unless the command declares and receives `--migrate` / `--build`.

Register both commands in the module's ServiceProvider inside
`if ($this->app->runningInConsole()) { $this->commands([...]); }`.
See [Reusable Traits](traits.md) for the traits and [Artisan Commands](artisan-commands.md)
for `noerd:update-all`.

### Modules that require another module

A module that cannot work without another tenant app — the CMS needs `MEDIA` for its image
pickers — declares it in the install command, as tenant-app key => install command:

```php
/**
 * @return array<string, string>
 */
protected function getRequiredModules(): array
{
    return ['MEDIA' => 'noerd:install-media'];
}
```

`runModuleInstallation()` installs every required module whose app is not registered yet, **as a
dependency** (`Noerd\Support\ModuleInstallContext`): the nested command publishes and registers
with its defaults but asks NOTHING — no app title, no tenants, no migration, no build, no closing
callout. The user started one installation and answers each question once. `--force` is forwarded.

The required app is then assigned to exactly the tenants the module's own app was assigned to, in
the same prompt. Assignment is **additive only**: deselecting a tenant removes the module's app but
never the required one, which another installed module may equally depend on. A required app whose
package is not installed is reported as a warning — it is a missing optional dependency, not a
reason to fail the installation. (`getRequiredAppKeys()` — by default the keys of
`getRequiredModules()` — may name an app that has no install command of its own.)

### npm runs once, at the end

When `noerd:install-{module}` installs the base package on the way (fresh project), the base
installer does not run node: `npm install` and `npm run build` are deferred to the END of the module
installation, so they see the module's files too, and the build question is asked once. A module
command needs no code for it (`askForNpmBuild()` / a support module's `finishDeferredNpm()` pick the
work up); if the installation dies first, it says which command to run by hand.

### The closing callout

`runModuleInstallation()` ends with ONE `{Module} is ready` box linking the URL of the route
`getAppRoute()` names (e.g. `/cms`), or `/noerd-apps` when that route is not registered. A module
installed as a dependency and the base installer running for a module install print no box.

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
| `app-configs/{module}/` | YAML configuration templates (`lists/`, `details/`, `pages/`, `navigation.yml`; add `settings/` by hand when the module has a settings page) — copied into the project by the install command; the generators write both copies, keep them in sync |
| `app-configs/stubs/add_{module}_tenant_app.php.stub` | The tenant-app migration published by the install command |
| `database/migrations/`, `database/factories/`, `database/seeders/` | Database migrations, factories and seeders (module-owned) |
| `resources/boost/guidelines/core.blade.php` | Module-specific rules for AI coding agents, rendered by Laravel Boost; the install/update command registers the package in the host's `boost.json` (see [AI Agents](ai-agents.md)) |
| `skills/{name}/SKILL.md` | Claude Code skills shipped with the module — **top-level**, next to `src/` (the install/update command publishes every subfolder into the project's `.claude/skills/`). Only the noerd package itself keeps its skills in `resources/boost/skills/` |
| `resources/lang/de.json` | Translations (English key → German) |
| `resources/views/components/` | Livewire single-file components (`{module}-dashboard.blade.php`, `*-list.blade.php`, `*-detail.blade.php`, `*-page.blade.php`, `*-modal.blade.php`) — flat, no subfolders |
| `routes/{module}-routes.php` | Route definitions |
| `src/Commands/` | `{Module}InstallCommand`, `{Module}UpdateCommand` |
| `src/Models/` | Eloquent models (`$guarded`, `BelongsToTenant`) |
| `src/Providers/` | ServiceProvider |
| `tests/` | Pest tests (`tests/Components/` scaffolded), `tests/Traits/` for module test traits (see [Testing](testing.md)) |
| `AGENTS.md`, `CLAUDE.md` | Contributor notes for humans and AI agents working on the module |

## Next Steps

- [List View](list-view.md) - Customize list views
- [Detail View](detail-view.md) - Customize detail forms
- [Field Types](field-types.md) - Full YAML field reference
- [Testing](testing.md) - Testing module components
- [AI Agents](ai-agents.md) - Boost guidelines and skills shipped with noerd and your module
