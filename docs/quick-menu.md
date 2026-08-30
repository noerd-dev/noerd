# Quick-Menu

The Quick-Menu displays action buttons in the header area for fast access to common functions.

## Buttons Are App-Independent

The quick-menu is tenant scoped, not app scoped: it renders the same buttons with the same targets
no matter which app is selected in the app bar. A quick-menu button must therefore never read
`TenantHelper::getSelectedApp()` (directly or through a helper that falls back to it) to decide
where it links or what it shows.

When a button's target only makes sense per app, render **one button per app the tenant runs**
instead of one button that changes meaning — e.g. a tenant running three shop modules gets three
shop buttons, each pinned to its own module:

```php
foreach ($shopModuleService->modulesForTenant($tenant) as $module) {
    $this->shopLinks[] = [
        'label' => $titles[$module->value],
        'url' => DomainHelper::url($tenant->id, $module), // module passed explicitly
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
  - policy: canOrders
    component: quick-menu.open-orders
  - policy: canCms
    component: quick-menu.website-link
```

## Button Properties

| Property | Description |
|----------|-------------|
| `app` / `apps` | Tenant app name (string) or list of names (`tenant_apps.name`) the button belongs to. The button renders only when at least ONE of them is assigned to the selected tenant AND the app permission allows it (`AccessHelper::canUseApp()`). Set it on every button that opens an app's screens or shows an app's data — this replaces the removed per-module tenant gates (`canOrders` & Co.). |
| `policy` | (optional) Gate ability or policy name for access control — only for checks beyond app usability |
| `component` | Livewire component to render |

## Policy-Based Access Control

Each button requires a policy check. The button is only displayed if the user passes it. The check
tries a `Gate::define()` ability first; if no gate with that name exists, it falls back to the
ability on the `Tenant` model policy:

```yaml
buttons:
  - policy: canOrders
    component: quick-menu.open-orders
```

The user must have the `canOrders` ability for this button to appear.

## Creating a Quick-Menu Button

Components are placed in your module's views directory and referenced with the `quick-menu.{name}`
prefix:

```
app-modules/{module}/resources/views/components/quick-menu/{name}.blade.php
```

### Example: Open Orders Button

`app-modules/my-module/resources/views/components/quick-menu/open-orders.blade.php`

```php
<?php

use Livewire\Component;
use MyVendor\MyModule\Models\Order;

new class extends Component {
    public int $openOrders = 0;

    public function mount(): void
    {
        $this->refreshOrderCount();
    }

    public function refreshOrderCount(): void
    {
        $this->openOrders = Order::where('status', 'open')->count();
    }
}; ?>

<div class="hidden lg:flex" wire:poll.15s="refreshOrderCount">
    <x-noerd::button
        variant="pill"
        @click="$modal('orders-list', {{ json_encode(['filter' => 'open']) }})"
        :class="$openOrders > 0 ? '!bg-red-300' : ''"
    >
        {{ __('Open Orders') }}: {{ $openOrders }}
    </x-noerd::button>
</div>
```

### Example: Website Link Button

`app-modules/cms/resources/views/components/quick-menu/website-link.blade.php`

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

A module's install command adds its quick-menu button idempotently via
`HasModuleInstallation::ensureQuickMenuButton()` — the entry is appended to
`app-configs/quick-menu.yml` only when not already present:

```php
$this->ensureQuickMenuButton([
    'policy' => 'canOrders',
    'component' => 'quick-menu.open-orders',
]);
```

See [Creating Modules](creating-modules.md) for the install-command context.

## Key Concepts

- **App-independent:** Buttons never depend on the selected app — see above
- **Component prefix:** Use `quick-menu.{name}` to reference components in the `quick-menu/` subdirectory
- **Responsive:** Use `hidden lg:flex` to show buttons only on larger screens
- **Polling:** Use `wire:poll` for live updates (e.g., order counts)
- **Modal integration:** Use `@click="$modal('component-name')"` to open modals
- **Styling:** Use `<x-noerd::button variant="pill">` — it follows the active theme and brand;
  don't hand-roll Tailwind button classes
- **Tenant switcher:** The quick-menu row also hosts the tenant switcher (shown when
  `noerd.features.multi_tenant` is on and the user has more than one tenant)
