# Quick-Menu

The Quick-Menu displays action buttons in the header area for fast access to common functions.

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
| `policy` | Gate ability or policy name for access control |
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

- **Component prefix:** Use `quick-menu.{name}` to reference components in the `quick-menu/` subdirectory
- **Responsive:** Use `hidden lg:flex` to show buttons only on larger screens
- **Polling:** Use `wire:poll` for live updates (e.g., order counts)
- **Modal integration:** Use `@click="$modal('component-name')"` to open modals
- **Styling:** Use `<x-noerd::button variant="pill">` — it follows the active theme and brand;
  don't hand-roll Tailwind button classes
- **Tenant switcher:** The quick-menu row also hosts the tenant switcher (shown when
  `noerd.features.multi_tenant` is on and the user has more than one tenant)
