# Settings Pages

A settings page is a **tenant-singleton form**: it edits one row per tenant (keyed by
`tenant_id`) instead of an addressable record. The `NoerdSettingsPage` trait makes such a
page as slim as the simplest detail — tabs and fields come from a **settings YAML**, the
component only declares which models the page edits.

Settings pages differ from details/pages in four hard rules:

1. **Always YAML-configured** — the layout comes exclusively from
   `settings/{component}.yml`. Layout overrides never apply (the settings loader skips
   the override hook entirely), and there are no `custom_attributes` fields.
2. **No grid** — every field renders stacked, full width, in the built-in `settings`
   theme. A `theme:` key in the YAML is ignored; the tenant-wide form theme (even an
   enforced one) does not apply either.
3. **No URL model parameter** — by convention a settings page declares no `$detailPrimary`
   and never uses `$modelId`; the URL stays clean (`/inventory/settings`, not `?settingsId=`).
4. **No delete path** — the singleton row is created on first save and never deleted from the
   page.

The models behind a settings page are **tenant singletons** — see
[Tenant Singleton Models](#tenant-singleton-models) below for the trait they use.

## Component

`resources/views/components/{name}-page.blade.php` — a settings component keeps the
`*-page` suffix:

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdSettingsPage;
use Vendor\Module\Models\ModuleSettings;

new class extends Component {
    use NoerdSettingsPage;

    public array $settingsModels = [
        'detailData' => ModuleSettings::class,
    ];
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Module Settings') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId"/>

    <x-slot:footer>
        <x-noerd::delete-save-bar :show-delete="false"/>
    </x-slot:footer>
</x-noerd::page>
```

`$settingsModels` maps **public array properties to tenant-singleton models**. Every key
is the data array the YAML fields bind to; `detailData` is provided by the trait, extra
keys are declared on the component. A settings page may edit SEVERAL models at once:

```php
public array $settingsModels = [
    'detailData' => InventorySettings::class,
    'notificationData' => NotificationSettings::class,
];

public array $notificationData = [];
```

On mount each model is resolved for the selected tenant — `Model::forTenant($tenantId)` for a
[`TenantSingleton`](#tenant-singleton-models) model (the row, or its defaults while the tenant
has none), `firstOrNew(['tenant_id' => $tenantId])` for any other — and hydrated into its
property; `store()` validates from the layout and persists every model (`updateForTenant()`,
else `updateOrCreate(['tenant_id' => $tenantId], …)`), writing only the keys the settings YAML
binds for that property (`id`, `tenant_id` and timestamps are always stripped). Both are
guarded: `canWriteObject()` checks the object-write gate of every declared model.
`canSaveObject()` — what the save button and the save shortcut key off — is overridden to
mean exactly that write check (saving a singleton is always a write, even when the row
does not exist yet); `canDeleteObject()` checks the delete gate of every declared model.

### Custom mount / store

The trait composes `NoerdDetail` (`validateFromLayout()`, relation fields, picklist
providers and `setFieldValue` work as in a detail), and its methods are overridable. A custom `mount()` starts with
`$this->initSettings()` (protected); a custom `store()` (extra validation, cache busting)
keeps the guard and ends with the reusable tail:

```php
public function mount(): void
{
    $this->initSettings();

    // a value the YAML cannot express (the defaults of a tenant without a row
    // belong in the model's tenantDefaults(), not here)
    $this->paymentOptions = app(PaymentMethodService::class)->methodsFor($this->tenantId());
}

public function store(): void
{
    if (! $this->canWriteObject()) {
        return;
    }

    // ... custom validation ($this->validate([...])) ...

    $this->validateFromLayout();

    $this->persistSettings();

    $this->showSuccessIndicator = true;
}
```

## Tenant Singleton Models

A model with ONE row per tenant — a module's settings, a tenant's counters — uses
`Noerd\Traits\TenantSingleton` and declares `implements Noerd\Contracts\TenantSingletonContract`
(what the settings page keys off). The trait is the single lookup such a model offers, so no module
writes its own `forTenant()` / `resolveForTenant()` / `firstWhere('tenant_id', …)` variant:

```php
use Noerd\Contracts\TenantSingletonContract;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\TenantSingleton;

class HrSetting extends Model implements TenantSingletonContract
{
    use BelongsToTenant;
    use TenantSingleton;

    protected $guarded = ['id'];

    /** What a tenant without a row behaves with. */
    public static function tenantDefaults(): array
    {
        return ['default_vacation_days' => 30, 'approval_required' => true];
    }
}

HrSetting::forTenant()->default_vacation_days;        // the current tenant's row — or the defaults
HrSetting::forTenant($tenantId)->approval_required;   // any tenant, past the tenant scope (jobs, commands)
HrSetting::existsForTenant($tenantId);                // whether a row was saved at all
HrSetting::updateForTenant(['approval_required' => false], $tenantId);  // creates the row on the first call
```

| Method | Behaviour |
|--------|-----------|
| `forTenant(?int $tenantId = null): static` | The tenant's row, or an unsaved instance carrying `tenantDefaults()` (`exists === false`). Without an argument the current tenant context decides — the signed-in user's selection, or the tenant a job/command entered through `TenantContext`; with no tenant at all it throws instead of guessing. Memoized per request |
| `tenantDefaults(): array` | Override: the attribute values of a tenant without a row (`[]` by default) |
| `existsForTenant(?int $tenantId = null): bool` | Whether the tenant has a saved row |
| `updateForTenant(array $attributes, ?int $tenantId = null): static` | Fills and saves the tenant's row — created on the first call; `$guarded` applies |
| `forgetTenantSingletons(): void` | Drops the request memo (done automatically on save and delete) |

The lookup lifts every global scope on purpose — a tenant scope would hide another tenant's row
from a scheduled job, and a singleton never soft-deletes. `NoerdSettingsPage` reads and writes a
`TenantSingleton` model through these methods; the core's own `NoerdSettings` uses the trait too
(its `forTenant()` answers an empty instance instead of throwing when no tenant is known at all —
the formatting helpers run for guests and fall back to the config).

## Settings YAML

`app-configs/{app}/settings/{component}.yml` — plus the module copy at
`app-modules/{module}/app-configs/{app}/settings/`, both kept in sync like every other
config. Resolution follows the standard search roots (project copy shadows module copy).
Allowed keys: `title`, `description`, `tabs`, `fields`. Fields use the same field types,
`tab:`, `required:`, `showIf`/`showIfNot` and `helpText` as detail YAMLs — `colspan` is
irrelevant (every field is a full-width row).

```yaml
title: Module Settings
tabs:
  - number: 1
    label: General
  - number: 2
    label: Notifications
fields:
  - name: detailData.name
    label: Name
    type: text
    required: true
  - name: detailData.get_email
    label: Send an email for each order?
    type: checkbox
    tab: 2
  - name: detailData.notification_emails
    label: Notification email addresses
    type: text
    tab: 2
    showIf: detailData.get_email
    helpText: Multiple addresses separated by commas.
```

Loading goes through `StaticConfigHelper::getSettingsFields()`, which deliberately skips
the layout-override hook and the tenant theme setting and forces `theme: settings`.
Select options may come from component methods (`optionsMethod:` on `type: select`,
`picklistField:` on `type: picklist`) exactly like in a detail YAML.

The `settings` theme is an internal theme (`hidden: true`, `fullWidthRows: true`; every
element falls back to the default theme) — see [Themes](themes.md). The `settings/` folder
is published by the module install/update commands exactly like `lists/`, `details/` and
`pages/`.

## Reference

The shipped in-package example is the setup app's **System Settings** page
(`noerd::system-settings-page`, `resources/views/components/system-settings-page.blade.php`
with `app-configs/setup/settings/system-settings-page.yml`): one model (`NoerdSettings`),
select options from component methods (`currencyOptions()`, `localeOptions()`, `themeOptions()`), effective
defaults set in a custom `mount()`, and a custom `store()` with extra validation and cache
busting that ends in the trait tail. Trait mechanics are covered by
`tests/Feature/SettingsPageTraitTest.php` and `tests/Feature/SettingsPageWriteGuardTest.php`.
