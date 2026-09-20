# Banner System

Banners display important notifications at the top of the application. They can be static messages
or dynamic components. Only **one** banner is visible at a time: the highest-priority banner the
user has not dismissed.

## File Location

```text
app-configs/banner.yml
```

## Configuration

```yaml
banners:
  - priority: 100
    type: danger
    message: "Important maintenance tonight!"
    dismissible: false

  - priority: 50
    type: warning
    component: inventory::banner.demo-expiry
    dismissible: true

  - priority: 10
    type: info
    message: "New features available!"
    dismissible: true
```

## Banner Properties

| Property | Description |
|----------|-------------|
| `priority` | Required — entries without a priority are ignored. The highest-priority active banner is shown |
| `type` | Visual style: `danger`, `warning`, `info`, `success`; omitted or unknown → `warning` |
| `message` | Static text, rendered as written — it is NOT passed through `__()`. Use a `component` for a translated or computed text |
| `component` | Dynamic Livewire component (alternative to `message`), namespaced: `{module}::banner.{name}` |
| `dismissible` | Allow users to close the banner |

## Banner Types

| Type | Color | Use Case |
|------|-------|----------|
| `danger` | Red | Critical issues, system errors |
| `warning` | Yellow | Important notices, expiring features |
| `info` | Blue | General information, announcements |
| `success` | Green | Positive confirmations |

## Creating a Dynamic Component

Components are placed in your module's views directory and referenced by their namespaced Livewire
name with the `banner.` prefix:

```text
app-modules/{module}/resources/views/components/banner/{name}.blade.php   → {module}::banner.{name}
```

### Example: Demo Expiry Banner

`app-modules/inventory/resources/views/components/banner/demo-expiry.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public string $message = '';

    public function mount(): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $daysRemaining = 0;

        if ($tenant && isset($tenant->demo_expires_at)) {
            $daysRemaining = now()->diffInDays($tenant->demo_expires_at, false);
            $daysRemaining = max(0, (int) $daysRemaining);
        }

        $this->message = __('Your demo expires in :days days', ['days' => $daysRemaining]);
    }
}; ?>

<span>{{ $message }}</span>
```

Translation labels use English text as the key; add the German mapping to the module's `de.json`
(e.g. `"Your demo expires in :days days": "Ihre Demo läuft in :days Tagen ab"`).

## Key Concepts

- **Single active banner:** only the highest-priority non-dismissed banner renders; dismissing it
  reveals the next one. A non-dismissible banner stays until it is removed from the configuration
- **Dismissals** live in the session (`dismissed_banners`) and reset with it. They are stored by
  position in the priority-sorted list, so adding or removing a banner shifts which one a user has
  dismissed
