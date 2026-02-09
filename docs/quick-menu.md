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
  - policy: canOrders
    component: quick-menu.shop-link
  - policy: canCms
    component: quick-menu.website-link
  - policy: canTimes
    component: quick-menu.next-slot
```

## Button Properties

| Property | Description |
|----------|-------------|
| `policy` | Gate/policy name for access control |
| `component` | Livewire component to render |

## Policy-Based Access Control

Each button requires a policy check. The button is only displayed if the user passes the policy check.

```yaml
buttons:
  - policy: canOrders
    component: quick-menu.open-orders
```

The user must have the `canOrders` permission (gate) for this button to appear.

## Creating a Quick-Menu Button

Components are placed in your module's views directory:

```
app-modules/{module}/resources/views/components/quick-menu/{name}.blade.php
```

### Example: Open Orders Button

`app-modules/liefertool/resources/views/components/quick-menu/open-orders.blade.php`

```php
<?php

use Livewire\Component;
use Nywerk\Liefertool\Models\LiefertoolTenant;

new class extends Component {
    public $openOrders;

    public function mount()
    {
        $user = auth()->user();
        if ($user && $user->can('canOrders')) {
            $selectedTenant = LiefertoolTenant::find($user->selected_tenant_id);
            $this->openOrders = $selectedTenant?->openOrders()->count();
        }
    }

    public function refreshOrderCount()
    {
        if (auth()->user()->can('canOrders')) {
            $selectedTenant = LiefertoolTenant::find(auth()->user()->selected_tenant_id);
            $this->openOrders = $selectedTenant?->openOrders()->count();
        }
    }
}; ?>

<div class="hidden lg:flex" wire:poll.15s="refreshOrderCount">
    <button
        @click="$modal('orders-list', {{ json_encode(['filter' => 'open']) }})"
        @class([
            'bg-gray-100 rounded-lg my-auto text-sm px-3 py-1',
            'bg-red-300' => $openOrders > 0,
        ])
    >
        {{ __('Open Orders') }}: {{ $openOrders }}
    </button>
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
    <a href="{{ $websiteUrl }}" target="_blank"
       class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1 hover:bg-gray-200">
        {{ __('Website') }}
    </a>
</div>
```

## Key Concepts

- **Component prefix:** Use `quick-menu.{name}` to reference components in the `quick-menu/` subdirectory
- **Responsive:** Use `hidden lg:flex` to show buttons only on larger screens
- **Polling:** Use `wire:poll` for live updates (e.g., order counts)
- **Modal integration:** Use `@click="$modal('component-name')"` to open modals

## Styling Guidelines

Recommended Tailwind classes for consistency:

```blade
<button class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1 hover:bg-gray-200">
    Button Text
</button>
```

For highlighting important states:

```blade
<button @class([
    'bg-gray-100 rounded-lg my-auto text-sm px-3 py-1',
    'bg-red-300' => $hasUrgentItems,
])>
    {{ $count }} Items
</button>
```

## Examples

### Counter with Live Updates

```yaml
buttons:
  - policy: canOrders
    component: quick-menu.open-orders
```

Component with polling:

```php
<div wire:poll.15s="refreshCount">
    <button>{{ $count }} Items</button>
</div>
```

### External Link

```yaml
buttons:
  - policy: canCms
    component: quick-menu.website-link
```

Simple link button:

```blade
<a href="{{ $url }}" target="_blank" class="...">
    {{ __('Visit Website') }}
</a>
```
