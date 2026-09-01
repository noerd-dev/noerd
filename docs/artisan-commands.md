# Artisan Commands

The Noerd Framework provides the following Artisan commands.

In addition, every installed app module ships its own `noerd:install-{module}` command — generated
by the `noerd:module` scaffolder, see [Creating Modules](creating-modules.md) — plus a hand-written
`noerd:update-{module}` counterpart that republishes its YAML configs. `noerd:update-all` runs all
of them at once.

## Overview

### Installation & Maintenance

| Command | Description |
|---------|-------------|
| `noerd:install` | Install noerd content to the local content directory |
| `noerd:update` | Update noerd content files without running installation setup |
| `noerd:update-all` | Run `noerd:update` and every installed module's `noerd:update-{module}` command |
| `noerd:demo` | Install demo data (models, migrations, views, configs, routes) |
| `noerd:publish-home` | Publish the noerd-apps view for customization |
| `noerd:info` | Display the current Noerd version |

### Users, Tenants & Apps

| Command | Description |
|---------|-------------|
| `noerd:create-admin` | Create a new user and make them an admin |
| `noerd:make-admin` | Make an existing user an admin on all their tenants |
| `noerd:create-tenant` | Create a new tenant with default profiles |
| `noerd:create-app` | Create a new app (TenantApp) with its own dashboard that can be assigned to tenants |
| `noerd:assign-apps-to-tenant` | Assign apps to a tenant with interactive selection |

### Scaffolding

| Command | Description |
|---------|-------------|
| `noerd:module` | Create a new module with complete directory structure |
| `noerd:make-resource` | Generate list + detail Blade and YAML files from an existing Eloquent model |
| `noerd:make-list` | Generate a list Blade file from an existing Eloquent model |
| `noerd:make-detail` | Generate a detail Blade file with YAML from model columns |
| `noerd:make-page` | Generate a standalone page Blade file (no model required) |
| `noerd:make-dashboard` | Generate a dashboard Blade file for an app |
| `noerd:make-collection` | Create a new collection YML file interactively |
| `noerd:theme` | Scaffold a new form theme by copying the default theme folder |

### Setup Collections

| Command | Description |
|---------|-------------|
| `noerd:setup-collections:import-yaml` | Import setup collection definitions from YAML into the database |
| `noerd:setup-collections:export-yaml` | Export setup collection definitions from the database as YAML |

## noerd:install

Installs the Noerd Framework and performs basic configuration: default tenant, admin user, and
optionally demo data.

```bash
php artisan noerd:install
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing files without asking |

## noerd:update

Updates noerd content files (frontend assets, published config) without running the full
installation setup.

```bash
php artisan noerd:update
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing files without asking |
| `--build` | Run npm build after update |

## noerd:update-all

Refreshes a project after pulling new module versions: runs `noerd:update` and the
`noerd:update-{module}` command of every installed module in one go.

```bash
php artisan noerd:update-all --force
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing files without asking (forwarded to every sub-command) |
| `--build` | Run npm build — applies to `noerd:update` only, no module command defines it |
| `--except=` | Skip a command; module key or full name (`--except=cms`, `--except=noerd:update`). Repeatable |

**Run order:** `noerd:update` → the module updates alphabetically → commands implementing
`Noerd\Contracts\RunsAfterModuleUpdates` last. The marker is for a module update that touches what
the core publishes (e.g. one that re-adds its entries to `app-configs/setup/navigation.yml`, which
`noerd:update --force` rewrites from the core template). The module updates in between only write
into their own `app-configs/{module}/` and are sorted purely for a reproducible run.

**Discovery** is dynamic: every command named `noerd:update-{module}` that is registered by a loaded
service provider takes part — nothing is hardcoded, so a newly installed module is picked up
automatically and a module that is not installed is simply absent.

**Without `--force`** every sub-command asks per existing file (default: `skip`), so the command
warns and asks once up front whether to proceed. Combined with `--no-interaction` all existing files
are skipped and only missing files are copied — a useful "top up a fresh checkout" mode.

> `noerd:update --force` does more than copy YAML: it also overwrites `config/noerd.php`, scaffolds
> the missing frontend files (see [Installation](installation.md#frontend)), runs `npm install` for
> any dependency it added.
> Use `--except=noerd:update` to leave the core step out.

**Failures do not abort the run.** Every command is executed, a summary table lists each one as
`updated`, `failed` or `skipped`, and the exit code is non-zero if any of them failed.

## noerd:demo

Installs demo data into your project. This publishes a fully working Demo Customers app with model,
migration, Blade components, YAML configuration, navigation, and routes. The demo app is
automatically registered as a TenantApp and assigned to all tenants.

```bash
php artisan noerd:demo
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing files |
| `--migrate` | Run the migrations — required to migrate in non-interactive runs (interactive runs ask) |
| `--seed` | Seed the demo data — required to seed in non-interactive runs (interactive runs ask) |

This command is also offered during `noerd:install` (which forwards its own `--migrate` as
`--migrate --seed`). It can be run independently at any time.

## noerd:publish-home

Publishes the `noerd-apps` dashboard view into the project so it can be customized (e.g. to embed
[dashboard widgets](dashboard-widgets.md)).

```bash
php artisan noerd:publish-home
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing file |

## noerd:info

Displays the currently installed Noerd version.

```bash
php artisan noerd:info
```

## noerd:create-admin

Creates a new user and makes them an administrator.

```bash
php artisan noerd:create-admin
```

| Option | Description |
|--------|-------------|
| `--name=` | The name of the user |
| `--email=` | The email of the user |
| `--password=` | The password of the user |
| `--super-admin` | Mark this user as super admin |

## noerd:make-admin

Makes an existing user an administrator by giving them admin profile access on all their tenants.

```bash
php artisan noerd:make-admin {user_id}
```

## noerd:create-tenant

Creates a new tenant with default profiles.

```bash
php artisan noerd:create-tenant
```

| Option | Description |
|--------|-------------|
| `--name=` | The name of the tenant |
| `--default` | Use "Default" as the tenant name |

## noerd:create-app

Creates a new app (TenantApp) that can be assigned to tenants. Without options the command runs as
an interactive wizard. Every app comes with its own dashboard: the command runs
`noerd:make-dashboard` for the new app and stores the generated `{app}.dashboard` route as the
app's main route (see [Create an App](create-app.md)).

```bash
php artisan noerd:create-app
```

| Option | Description |
|--------|-------------|
| `--title=` | The display title of the app |
| `--name=` | The unique name identifier of the app (uppercase) |
| `--icon=` | The icon identifier for the app (Heroicon, searchable in the wizard) |
| `--route=` | Open an existing route (e.g. `crm.index`) instead of generating a dashboard |
| `--active=1` | Whether the app is active (`1` or `0`) |

## noerd:assign-apps-to-tenant

Assigns apps to a tenant with interactive selection.

```bash
php artisan noerd:assign-apps-to-tenant
```

| Option | Description |
|--------|-------------|
| `--tenant-id=` | The ID of the tenant to assign apps to |

## noerd:module

Creates a new module with complete directory structure, including model, migration, Livewire
components, YAML configurations, translations, an install command, and the ServiceProvider.

```bash
php artisan noerd:module
# or with module name
php artisan noerd:module inventory
```

See [Creating Modules](creating-modules.md) for the generated structure.

## noerd:make-resource

Generates list and detail Blade components along with their YAML configuration files from an
existing Eloquent model. The command reads the model's database columns and automatically maps them
to appropriate YAML field types.

```bash
# With full namespace
php artisan noerd:make-resource "App\Models\Invoice"

# Short name (resolves via the configured module search, see noerd.generators)
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

The command prompts you to select which app the resource belongs to from all active entries in
`tenant_apps`. The module name is auto-detected from the model's namespace via `composer.json`
autoload mappings. Short model names are resolved through `noerd.generators.search_modules` /
`noerd.generators.modules_path`.

### Database column type mapping

Columns `id`, `tenant_id`, `created_at`, `updated_at`, and `deleted_at` are excluded. The remaining
columns are mapped by their database type:

| Database type | Detail field type | List column type |
|---------------|-------------------|------------------|
| `varchar`, `string`, `char` | `text` | `text` |
| `text`, `longtext`, `mediumtext` | `textarea` | `text` |
| `tinyint`, `boolean` | `checkbox` | `boolean` |
| `integer`, `bigint`, `smallint`, `decimal`, `float`, `double` | `number` | `number` |
| `date` | `date` | `date` |
| `datetime`, `timestamp` | `datetime` | `datetime` |
| `json` | `textarea` | — |

The generated list YAML contains at most 8 columns.

## noerd:make-list

Generates only the list Blade file (plus YAML) from an existing Eloquent model.

```bash
php artisan noerd:make-list "Noerd\Customer\Models\Customer"
```

| Option | Description |
|--------|-------------|
| `--app=` | App name (e.g. `crm`) — skips the interactive app selection |

## noerd:make-detail

Generates only the detail Blade file with YAML from the model columns.

```bash
php artisan noerd:make-detail "Noerd\Customer\Models\Customer"
```

| Option | Description |
|--------|-------------|
| `--app=` | App name (e.g. `crm`) — skips the interactive app selection |

## noerd:make-page

Generates a standalone page Blade file. No model is required — use this for settings screens or
custom pages.

```bash
php artisan noerd:make-page sent-mails --app=crm
```

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Page name in kebab-case (e.g. `sent-mails`) |
| `--app=` | App name (e.g. `crm`) |

## noerd:make-dashboard

Generates a dashboard Blade file for an app (`resources/views/components/{app}-dashboard.blade.php`),
adds the `{app}.dashboard` route to `routes/web.php` (inside the `noerd` + `app-access:{app}`
middleware group) and links it from the app's `navigation.yml` — creating the navigation file when
the app has none yet. `noerd:create-app` runs this command automatically for every new app.

```bash
php artisan noerd:make-dashboard --app=crm
```

| Option | Description |
|--------|-------------|
| `--app=` | App name (e.g. `crm`) |

## noerd:make-collection

Creates a new collection YML file interactively. See [Setup Collections](setup-collections.md).
Has no effect while `noerd.collections.mode` is `database` — the written file is not read there
(the command warns); create the collection in Setup → Collection Definitions or import the file
afterwards.

```bash
php artisan noerd:make-collection
# or non-interactive
php artisan noerd:make-collection customers --app=setup
```

| Argument / Option | Description |
|-------------------|-------------|
| `name` | The collection name (kebab-case, e.g. `customers`) |
| `--app=setup` | The target app folder in `app-configs` (default `setup`) |

## noerd:theme

Scaffolds a new form theme by copying the default theme folder. See [Themes](themes.md).

```bash
php artisan noerd:theme mytheme
# inside a module
php artisan noerd:theme mytheme --module=mymodule
```

| Argument / Option | Description |
|-------------------|-------------|
| `name` | The theme name (folder name, kebab-case) |
| `--module=` | Create the theme inside `app-modules/{module}` instead of the project |

## noerd:setup-collections:import-yaml

Imports setup collection definitions from YAML files into the `setup_collection_definitions` table.
Used when switching `noerd.collections.mode` to `database`, and again whenever a module update
ships a new collection YAML — newly published YAMLs are not picked up on their own. Idempotent:
existing definitions are updated in place. Newly created tenants are seeded automatically. See
[Setup Collections](setup-collections.md).

```bash
php artisan noerd:setup-collections:import-yaml --all-tenants
```

| Option | Description |
|--------|-------------|
| `--tenant-id=` | Import definitions for a specific tenant ID |
| `--all-tenants` | Import definitions for every tenant |
| `--delete` | Delete source YAML files after a successful import |
| `--dry-run` | Show what would happen without writing anything |

## noerd:setup-collections:export-yaml

Exports setup collection definitions from the database as YAML files. Used when switching
`noerd.collections.mode` back to `yaml`.

```bash
php artisan noerd:setup-collections:export-yaml --tenant-id=1
```

| Option | Description |
|--------|-------------|
| `--tenant-id=` | Export definitions for a specific tenant ID |
| `--delete` | Delete the database rows after a successful export |
| `--force` | Overwrite existing YAML files |
