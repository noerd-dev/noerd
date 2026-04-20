# Upgrade Guide: 0.5 → 0.6

This guide covers everything you need to know when upgrading the noerd module from `v0.5.x` to `v0.6.0`.

There are four breaking changes plus a handful of new opt-in features. Allow about 30 minutes to work through this guide on a typical project.

## Before You Upgrade

1. Make sure your project is on `noerd v0.5.1` and all migrations are up to date.
2. Commit any pending work — the upgrade touches YAML files, language files and (optionally) the project config.
3. Have a database backup available. The `setup_languages` migration alters an existing table.

## Step 1 — Bump the Package and Run Migrations

```bash
composer require noerd/noerd:^0.6
php artisan migrate
```

The new migration `2026_04_14_000001_add_tenant_id_to_setup_languages_table.php` adds a `tenant_id` column to `setup_languages`, seeds Deutsch and English for every existing tenant, deletes legacy global rows, and creates the new composite unique index `[tenant_id, code]`.

## Step 2 — Re-publish the noerd Config

`config/noerd.php` gained a new `theme` block and a `features.currency` flag. Re-run the update command so the project config picks them up:

```bash
php artisan noerd:update
```

This calls `publishNoerdConfig()` and `setupFrontendAssets()`. If you have manually edited the project's `config/noerd.php`, merge the new keys yourself instead. The new keys are:

- `features.currency` — `NOERD_CURRENCY_ENABLED` (default `true`)
- `theme.active` — `NOERD_THEME` (default `default`)
- `theme.presets.*` — built-in presets `default`, `sand`, `white`
- `theme.overrides.*` — eleven `NOERD_COLOR_BRAND_*` env vars

## Breaking Change 1 — Registered Relation Field Types

The generic `type: relation` is no longer supported. Every relation must use an explicit registered type such as `customerRelation`, `pageRelation` or `vehicleRelation`.

### Before (v0.5)

```yaml
- name: detailData.customer_id
  label: Customer
  type: relation
  relationField: relationTitles.customer_id
  modalComponent: customers-list
  colspan: 6
```

### After (v0.6)

```yaml
- name: detailData.customer_id
  label: Customer
  type: customerRelation
  colspan: 6
```

`modalComponent` and `relationField` are no longer read for registered relations — the list component, detail component and title resolver come from the registry.

### Registering a Relation Type

Register the type in the service provider of the module that owns the target model:

```php
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Customer\Models\Customer;

$relationFieldRegistry = $this->app->make(RelationFieldRegistry::class);

$relationFieldRegistry->register('customerRelation', RelationFieldDefinition::model(
    listComponent: 'customers-list',
    detailComponent: 'customer-detail',
    modelClass: Customer::class,
    titleResolver: 'name',
));
```

`titleResolver` accepts either an attribute name (string) or a callable for custom formatting:

```php
$relationFieldRegistry->register('quoteRelation', RelationFieldDefinition::model(
    listComponent: 'quotes-list',
    detailComponent: 'quote-detail',
    modelClass: Quote::class,
    titleResolver: fn (Quote $quote): string => $quote->number,
));
```

### What Still Works

- The legacy `{entity}Selected` event (e.g. `customerSelected`) is still dispatched alongside the new generic `noerdRelationSelected` event, so existing `#[On(...)]` handlers in detail components continue to work.
- Relation values are still stored as IDs and displayed via `relationTitles[$fieldId]`.

### What Will Break

- Any YAML still using `type: relation` will fail explicitly during rendering.
- Unregistered relation types fail with an exception — this is intentional, so the typo surfaces early.

See [relation-field-types.md](relation-field-types.md) for the full registration reference.

## Breaking Change 2 — Tenant-Scoped `setup_languages`

`setup_languages` is no longer a global table. Each tenant now has its own independent set of languages.

- New column: `tenant_id` (non-nullable, foreign key to `tenants.id`, `onDelete cascade`).
- New unique index: `[tenant_id, code]` replaces the old `[code]` unique index.
- New default rows: every existing tenant gets `de` (default) and `en` seeded by the migration.

If you have custom code that reads `setup_languages` directly, add a tenant filter:

```php
SetupLanguage::query()
    ->where('tenant_id', auth()->user()->selected_tenant_id)
    ->where('is_active', true)
    ->get();
```

The `LanguageFilterTrait` and existing list components already do this for you.

## Breaking Change 3 — `noerd-settings` Renamed to `system-settings`

The route, navigation entry, and Blade view are renamed:

| v0.5 | v0.6 |
|------|------|
| `route('noerd-settings')` | `route('system-settings')` |
| `noerd-settings-detail.blade.php` | `system-settings-detail.blade.php` |
| Navigation key `noerd-settings` | `system-settings` |

Search your project for `noerd-settings` and update any hard-coded route names, navigation overrides, or view references.

## Breaking Change 4 — Translations Use English as Keys

Module translation keys have switched from prefixed German/legacy keys (e.g. `noerd_label_dashboard`) to plain English text (e.g. `Dashboard`). The English locale file is no longer needed.

### What Was Removed

- `app-modules/noerd/resources/lang/en.json` (deleted — English now works by Laravel's fallback).

### What Changed

- All YAML lists/details and the navigation file in `app-configs/setup/` now use English text directly.
- `de.json` was rewritten to map English text to German.

### What You Need to Do

1. If your project ships its own translation overrides keyed against the old `noerd_*` keys, re-key them to English:

   ```diff
   - "noerd_label_dashboard": "Übersicht"
   + "Dashboard": "Übersicht"
   ```

2. If you have project-specific YAML or Blade files that still reference old keys (`__('noerd_label_…')`), switch to the English text the key represents.
3. Module-specific German translations belong in your module's own `resources/lang/de.json`. Avoid duplicate keys with conflicting German values across modules — JSON translations share one flat namespace.

See the project-wide rule in `CLAUDE.md` under **Translations** for the full convention.

## What's New (Non-Breaking)

### Theme Presets

Pick a preset with `NOERD_THEME=default|sand|white` or fine-tune individual colors with the new `NOERD_COLOR_BRAND_*` env vars. Resolution lives in `Noerd\Services\ThemeService`:

```php
$colors = app(\Noerd\Services\ThemeService::class)->colors();
$css    = app(\Noerd\Services\ThemeService::class)->cssCustomProperties();
```

### `FieldTypeRegistry`

Custom YAML field types can now be registered centrally instead of being hard-coded in `block.blade.php`:

```php
use Noerd\Services\FieldTypeRegistry;
use Noerd\Support\FieldTypeDefinition;

app(FieldTypeRegistry::class)->register(
    'mySpecialPicker',
    FieldTypeDefinition::include('my-module::forms.special-picker'),
);
```

Use `FieldTypeDefinition::include()` for Blade partials and `FieldTypeDefinition::livewire()` for dedicated Livewire field components.

### `RelationFieldRegistry`

The mechanism behind Breaking Change 1 — covered in detail above and in [relation-field-types.md](relation-field-types.md).

### `<x-noerd::code-snippet>` Component

A new component for documentation-style code blocks with syntax highlighting and a copy-to-clipboard button. Used throughout the bundled UI library demo.

### Currency Feature Flag

`NOERD_CURRENCY_ENABLED=false` lets you hide currency-related UI on installations that don't need it.

## Verification

After upgrading:

1. Visit `/system-settings` — confirm the page loads under the new route.
2. Open any detail view that uses a relation field (e.g. customer or product) and confirm the picker opens, the title displays, and selection still works.
3. In a multi-tenant install, switch tenants and check that each tenant has its own language list under Setup → Languages.
4. Run the noerd test suite:

   ```bash
   php artisan test --compact app-modules/noerd
   ```
