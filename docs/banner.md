# Banner System

Banners display important notifications at the top of the application. They can be static messages or dynamic components.

## File Location

```
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
    component: banner.demo-expiry
    dismissible: true

  - priority: 10
    type: info
    message: "New features available!"
    dismissible: true
```

## Banner Properties

| Property | Description |
|----------|-------------|
| `priority` | Display order (higher = shown first) |
| `type` | Visual style: `danger`, `warning`, `info`, `success` |
| `message` | Static text message |
| `component` | Dynamic Livewire component (alternative to message) |
| `dismissible` | Allow users to close the banner |

## Banner Types

| Type | Color | Use Case |
|------|-------|----------|
| `danger` | Red | Critical issues, system errors |
| `warning` | Yellow | Important notices, expiring features |
| `info` | Blue | General information, announcements |
| `success` | Green | Positive confirmations |

## Static vs Dynamic Banners

**Static Banner:** Use `message` for simple text.

```yaml
- priority: 100
  type: danger
  message: "System maintenance at 2 AM"
  dismissible: false
```

**Dynamic Banner:** Use `component` for complex logic.

```yaml
- priority: 50
  type: warning
  component: banner.demo-expiry
  dismissible: true
```

## Creating a Dynamic Component

Components are placed in your module's views directory:

```
app-modules/{module}/resources/views/components/banner/{name}.blade.php
```

### Example: Demo Expiry Banner

`app-modules/liefertool/resources/views/components/banner/demo-expiry.blade.php`

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

        $this->message = __('noerd_banner_demo_expiry', ['days' => $daysRemaining]);
    }
}; ?>

<span>{{ $message }}</span>
```

## Key Concepts

- **Priority:** Determines display order when multiple banners are active
- **Component prefix:** Use `banner.{name}` to reference components in the `banner/` subdirectory
- **Dismissible banners:** Users can close them; preference is stored per session
- **Non-dismissible banners:** Always visible until removed from configuration

## Examples

### Maintenance Notice

```yaml
banners:
  - priority: 100
    type: danger
    message: "Scheduled maintenance on Sunday, 2-4 AM"
    dismissible: false
```

### Feature Announcement

```yaml
banners:
  - priority: 10
    type: info
    message: "Try our new reporting feature!"
    dismissible: true
```

### Trial Expiry Warning

```yaml
banners:
  - priority: 50
    type: warning
    component: banner.demo-expiry
    dismissible: true
```
