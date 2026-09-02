---
name: noerd-module-development
description: "Use this skill when creating a new Noerd module or tenant app, or when touching module-level plumbing: the noerd:module scaffolder, the mandatory noerd:install-{module} / noerd:update-{module} commands, the ServiceProvider, app-configs (lists/details/pages/navigation.yml and the rule to keep project and module copies in sync), navigation entries and heroicons, JSON translations (English keys, de.json), project-specific fields via custom_attributes, module independence, and shipping agent guidelines (Boost guideline, AGENTS.md) with the module. Triggers on 'create a module/app', 'add a navigation entry', 'install/update command', 'project-specific field', 'module structure'."
license: MIT
metadata:
  author: noerd
---

# Noerd Module Development

A module is a Composer package under `app-modules/{module}/` (namespace `Noerd\{Module}` by
convention) that ships a tenant app: YAML configs, Livewire components, model, migrations, routes,
translations, tests and an install/update command. Hard rules: `noerd/noerd` Boost guideline. Docs:
`vendor/noerd/noerd/docs/creating-modules.md`, `make-app.md`, `navigation.md`,
`artisan-commands.md`, `traits.md`, `ai-agents.md`.

## 1. Scaffold

```bash
php artisan noerd:make-app        # choose "Module": asks title, name, heroicon, then registers with Composer and installs (tenant assignment is the only question)
php artisan noerd:module            # the scaffolder itself: module name, app title, heroicon
                                    # scripted: noerd:module inventory --title=Inventory --icon=cube
composer update noerd/{module}
php artisan noerd:install-{module}  # copies YAML, registers the tenant app, migrates (--scaffold = the silent make-app run)
php artisan noerd:make-resource Item --app={module}   # per record type, after model + migration exist in the module
```

The scaffold is dashboard + plumbing, no model. `noerd:make-resource` / `make-list` / `make-detail`
/ `make-page` / `make-dashboard` detect a module app (`app-modules/{app}/composer.json`) and write
into it: components under `{app}::`, routes into `routes/{app}-routes.php`, YAML + navigation into
both copies.

Never write the `tenant_apps` row of a module by hand or via the root `noerd:make-app` flow —
the generated install command registers it (name = UPPERCASE module key, icon = the heroicon from
`getAppIcon()`, route = the module dashboard). A module ships no icon file; a Blade icon is only
the manual exception when no heroicon fits.

Generated layout (keep it — do not invent new base folders):

```
app-modules/{module}/
├── app-configs/{module}/            # YAML templates: lists/, details/, pages/, navigation.yml
├── app-configs/stubs/add_{module}_tenant_app.php.stub   # tenant-app migration published by the install command
├── database/{migrations,factories,seeders}/
├── resources/boost/guidelines/core.blade.php   # module-specific agent rules (Boost)
├── resources/lang/de.json           # English key → German
├── resources/views/components/      # {module}-dashboard.blade.php, *-list.blade.php, *-detail.blade.php, *-page.blade.php, *-modal.blade.php (flat)
├── routes/{module}-routes.php
├── src/{Commands,Models,Providers}/
├── tests/                           # Pest, incl. tests/Traits
├── AGENTS.md + CLAUDE.md            # contributor notes for the module
└── composer.json
```

## 2. Mandatory install + update commands

- `noerd:install-{module}` — extends `Illuminate\Console\Command`, uses `HasModuleInstallation` +
  `RequiresNoerdInstallation`, implements `getModuleName()`, `getModuleKey()`, `getDefaultAppTitle()`,
  `getAppIcon()`, `getAppRoute()`, `getSourceDir()`; `handle()` → `$this->runModuleInstallation()`.
- `noerd:update-{module}` — slim subclass whose `handle()` → `$this->runModuleUpdate()` plus only
  idempotent post-install steps. `noerd:update-all` discovers it by name; a missing one silently
  drops the module from project-wide updates.
- Register both in the ServiceProvider inside `if ($this->app->runningInConsole())`.
- The tenant app name stored in `tenant_apps` is the UPPERCASE module key (`INVENTORY`) — gates
  and test traits must match it exactly.

## 3. YAML configs — always two copies

| What | Module template | Installed project copy |
|---|---|---|
| lists | `app-modules/{module}/app-configs/{app}/lists/*-list.yml` | `app-configs/{app}/lists/` |
| details / pages | `…/details/*-detail.yml`, `…/pages/*-page.yml` | `app-configs/{app}/details/`, `pages/` |
| navigation | `…/navigation.yml` | `app-configs/{app}/navigation.yml` |

Change BOTH. The folder is always called `app-configs/` (never `app-contents/`). List YAMLs stay
flat in `lists/`. Block-style YAML only. Navigation icons are heroicons:

```yaml
- title: Module
  name: module
  route: module
  block_menus:
    - title: Overview
      navigations:
        - title: Things
          route: module.things
          heroicon: cube
          newRoute: module.thing.detail        # the "+" opens an empty record as a route modal
          newComponent: module::thing-detail   # fallback when the route is not registered
```

## 4. Translations

English text IS the key (`__('Invoice')`, YAML `label: Invoice`); only `resources/lang/de.json`
(`"Invoice": "Rechnung"`), loaded with `loadJsonTranslationsFrom()`. Omit entries where English =
German. The JSON namespace is flat across modules — avoid the same key with a different German value.

## 5. Project-specific fields → `custom_attributes`

Never change module code or module YAML for one project's fields. Add a `custom_attributes` JSON
column via a migration in the **project root**, cast `'custom_attributes' => 'array'` in the module
model, and read/write `$this->detailData['custom_attributes']['my_key']` in project-level views.

## 6. Independence

No `use` of another optional module's classes/views/YAML; tests, traits (`tests/Traits/`),
factories, seeders and migrations live inside the module; no module-specific code in the host
project's `DatabaseSeeder`. Models use `$guarded`, never `$fillable`.

Amounts, numbers and dates are never hard-coded (`number_format(..., ',', '.')`, `€`, `d.m.Y`):
backend UI → `CurrencyHelper::format()` / `FormatHelper::date()`; documents (PDF, receipt, customer
e-mail) → `CurrencyHelper::formatForDocument($x, $model->tenant_id)` / `FormatHelper::documentDate()`;
payment payloads → `CurrencyHelper::codeForTenant()`. See `docs/formatting.md`.

## 7. Agent-readable guidelines for the module

- `resources/boost/guidelines/core.blade.php` — module rules (purpose, YAML locations, component
  names, commands, test call). Blade-rendered by Boost: wrap literal `{{ }}`/`@` in `@verbatim`.
  The host enables it by adding `"noerd/{module}"` to the `packages` array in `boost.json` and
  running `php artisan boost:update`.
- `AGENTS.md` (+ `CLAUDE.md` containing `@AGENTS.md`) — contributor workflow for the module repo.
- Keep both current when the module gains features.

## Done when

- [ ] `noerd:install-{module}` AND `noerd:update-{module}` exist and are registered
- [ ] YAML in both locations, navigation with heroicons, `de.json` updated
- [ ] no cross-module dependency, tests/migrations inside the module
- [ ] Boost guideline + AGENTS.md of the module reflect the change
- [ ] Pest tests green, `vendor/bin/pint --dirty` run
