# Quick Menu

The quick menu displays action buttons in the header area for fast access to common functions.

## Buttons Are App-Independent

The quick menu is tenant scoped, not app scoped: it renders the same buttons with the same targets
no matter which app is selected in the app bar. A quick menu button must therefore never read
`TenantHelper::getSelectedApp()` (directly or through a helper that falls back to it) to decide
where it links or what it shows.

When a button's target only makes sense per app, render **one button per app the tenant runs**
instead of one button that changes meaning — e.g. a tenant running several storefront apps gets one
button per storefront, each pinned to its own app:

```php
foreach ($tenant->tenantApps as $tenantApp) {
    if (! AccessHelper::canUseApp($tenantApp->name)) {
        continue;
    }

    $this->storefrontLinks[] = [
        'label' => $tenantApp->title,
        'url' => route('storefront.home', ['app' => $tenantApp->name]), // app passed explicitly
    ];
}
```

App-specific entry points belong in that app's navigation, dashboard or header actions.

## File Location

```
app-configs/quick-menu.yml
```

## Configuration

```yaml
buttons:
  - app: INVENTORY
    component: inventory::quick-menu.low-stock
  - app: WEBSITE
    component: website::quick-menu.website-link
```

## Button Properties

| Property | Description |
|----------|-------------|
| `app` / `apps` | Tenant app name (string) or list of names (`tenant_apps.name`, uppercase) the button belongs to. The button renders only when at least ONE of them is assigned to the selected tenant AND the app permission allows it (`AccessHelper::canUseApp()`). Set it on every button that opens an app's screens or shows an app's data — it is the primary gate. |
| `policy` | (optional) A host-defined gate ability or policy name for checks beyond app usability (`AccessHelper::canPassGate()`) |
| `component` | Livewire component to render |

## Access Control

Buttons are gated by their `app`/`apps` key — a button without one renders for every user. The
optional `policy` key adds a second check for cases app usability cannot express (e.g. a feature only
some roles may see): `AccessHelper::canPassGate()` tries a `Gate::define()` ability of that name
first; if no gate with that name exists, it falls back to the ability on the `Tenant` model policy.
The gate or policy is defined by the host project — noerd ships none.

```yaml
buttons:
  - app: INVENTORY
    policy: viewStockLevels
    component: inventory::quick-menu.low-stock
```

The user must use the `INVENTORY` app AND pass `viewStockLevels` for this button to appear.

## Creating a Quick Menu Button

Components are placed in your module's views directory and referenced by their namespaced Livewire
name with the `quick-menu.` prefix:

```
app-modules/{module}/resources/views/components/quick-menu/{name}.blade.php   → {module}::quick-menu.{name}
```

### Example: Low Stock Button

`app-modules/inventory/resources/views/components/quick-menu/low-stock.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Inventory\Models\Item;

new class extends Component {
    public int $lowStockCount = 0;

    public function mount(): void
    {
        $this->refreshLowStock();
    }

    public function refreshLowStock(): void
    {
        if (! AccessHelper::canUseApp('INVENTORY')) {
            return;
        }

        $this->lowStockCount = Item::query()->where('stock', '<', 5)->count();
    }
}; ?>

<div class="hidden lg:flex" wire:poll.15s="refreshLowStock">
    <x-noerd::button
        variant="pill"
        @click="$modalRoute('inventory.items', { filter: 'low-stock' }, null, null, null, { rewriteUrl: false, fallbackComponent: 'inventory::items-list' })"
        :class="$lowStockCount > 0 ? '!bg-red-300' : ''"
    >
        {{ __('Low Stock') }}: {{ $lowStockCount }}
    </x-noerd::button>
</div>
```

### Example: Website Link Button

`app-modules/website/resources/views/components/quick-menu/website-link.blade.php`

```php
<?php

use Livewire\Component;

new class extends Component {
    public string $websiteUrl = '';

    public function mount(): void
    {
        $this->websiteUrl = config('app.website_url', '/');
    }
}; ?>

<div class="hidden lg:flex">
    <a href="{{ $websiteUrl }}" target="_blank">
        <x-noerd::button variant="pill">{{ __('Website') }}</x-noerd::button>
    </a>
</div>
```

## Registering a Button from a Module Installer

A module's install command adds its quick menu button idempotently via
`HasModuleInstallation::ensureQuickMenuButton()` — a new entry is prepended to
`app-configs/quick-menu.yml`; an entry with the same `component` is replaced by the installer's
definition (with the `apps` lists of both merged):

```php
$this->ensureQuickMenuButton([
    'app' => 'INVENTORY',
    'component' => 'inventory::quick-menu.low-stock',
]);
```

`ensureQuickMenuButton(array $button, array $legacyComponents = [])` matches on `component`;
entries still pointing at one of the `$legacyComponents` names (a renamed component) are rewritten
to the new name first.

See [Creating Modules](creating-modules.md) for the install-command context.

## Key Concepts

- **App-independent:** Buttons never depend on the selected app — see above
- **Component name:** `{module}::quick-menu.{name}` for components in the module's `quick-menu/` subdirectory
- **Responsive:** Use `hidden lg:flex` to show buttons only on larger screens
- **Polling:** Use `wire:poll` for live updates (e.g., stock counts)
- **Modal integration:** Open records with `$modalRoute(...)`, dialogs with `$modal(...)` — see [Modal System](modal.md)
- **Styling:** Use `<x-noerd::button variant="pill">` — it follows the active theme and brand;
  don't hand-roll Tailwind button classes
- **Tenant switcher:** The quick menu row also hosts the tenant switcher (shown when
  `noerd.features.multi_tenant` is on and the user has more than one tenant)
