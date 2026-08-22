# Settings Pages

A settings page is a **tenant-singleton form**: it edits one row per tenant (keyed by
`tenant_id`) instead of an addressable record. The `NoerdSettingsPage` trait makes such a
page as slim as the simplest detail — tabs and fields come from a **settings YAML**, the
component only declares which models the page edits.

Settings pages differ from details/pages in four hard rules:

1. **Always YAML-configured** — the layout comes exclusively from
   `settings/{component}.yml`. The noerd-pro layout manager never applies (no tenant or
   per-user overrides), and there is no `custom_attributes` object manager.
2. **No grid** — every field renders stacked, full width, in the built-in `settings`
   theme. A `theme:` key in the YAML is ignored; the tenant-wide form theme (even an
   enforced one) does not apply either.
3. **No URL model parameter** — a settings page never declares `$detailPrimary` and never
   uses `$modelId`; the URL stays clean (`/liefertool-settings`, not `?settingsId=`).
4. **No delete path** — the singleton row is created on first save
   (`updateOrCreate(['tenant_id' => …])`) and never deleted from the page.

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
    'detailData' => LiefertoolSettings::class,
    'coredata' => Coredata::class,
];

public array $coredata = [];
```

On mount each model is resolved with `firstOrNew(['tenant_id' => $tenantId])` and
hydrated into its property; `store()` validates from the layout and persists every model
with `updateOrCreate(['tenant_id' => $tenantId], …)` (stripping `id`, `tenant_id` and
timestamps).

### Custom mount / store

Like `NoerdDetail`, the trait methods are overridable. A custom `mount()` starts with
`$this->initSettings()`; a custom `store()` (extra validation, cache busting) ends with
the reusable tail:

```php
public function store(): void
{
    // ... custom validation, addError() + return on failure ...

    $this->validateFromLayout();

    $this->persistSettings();

    $this->showSuccessIndicator = true;
}
```

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

## The `settings` theme

The built-in theme folder `resources/views/themes/settings/` contains only a `theme.yml`
(`fullWidthRows: true` — every element template falls back to the default theme). It is
marked `hidden: true`, so it never appears in the Setup → System Settings theme picker.
See [Themes](themes.md).

## Install / update

The `settings/` folder is published by the module install/update commands exactly like
`lists/`, `details/` and `pages/` (`HasModuleInstallation`).

## Reference

In-tree reference implementations: `liefertool::liefertool-settings-page` (two models,
two tabs, custom `store()` validation, extra Blade in a `tab2` slot) and
`liefertool::sms-page` (the slimmest form — no methods at all). Trait mechanics are
covered by `tests/Feature/SettingsPageTraitTest.php`.
