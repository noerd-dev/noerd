# Dashboard Widgets

The apps dashboard (`noerd-apps`) can render a row of **widgets** below the tenant-app tile grid —
e.g. a card listing the open tables of a restaurant, sized as wide and tall as two app tiles.
Configuration mirrors the [quick menu](quick-menu.md): a single project-level YAML file that modules
write themselves into at install time.

## Configuration

File: `app-configs/dashboard-widgets.yml` (project level only — like `quick-menu.yml` there is no
module copy and no `StaticConfigHelper` involvement).

```yaml
widgets:
  - app: INVENTORY
    component: inventory::dashboard-widgets.low-stock
    width: 2
    height: 2
```

| Key | Description |
|-----|-------------|
| `app` / `apps` | Tenant app name (string) or list of names (`tenant_apps.name`, uppercase) the widget belongs to. The widget renders only when at least ONE of them is assigned to the selected tenant AND the app permission allows it (`AccessHelper::canUseApp()`). Set it on every widget that shows an app's data — it is the primary gate. |
| `policy` | (optional) A host-defined gate ability or policy name for checks beyond app usability, resolved by `AccessHelper::canPassGate()`: a `Gate::define`d ability of that name first, else the ability on the `Tenant` model policy. |
| `component` | Livewire component to render — bare (`quick-menu.low-stock`) or namespaced (`inventory::dashboard-widgets.low-stock`). |
| `width` | (optional) Width in app-tile units, default `1`. |
| `height` | (optional) Height in app-tile units, default `1`. |

### Tile-unit sizing

App tiles are `9rem` (`w-36`) with a `1.5rem` gutter (`mr-6`/`mt-6`). A widget spanning `n` units
measures `n * 9rem + (n - 1) * 1.5rem`, so a `width: 2` widget aligns exactly with two app tiles
(`19.5rem`). The renderer emits the size as an inline `style` (the values come from YAML at runtime,
so Tailwind's JIT can never generate classes for them).

The widget row is hidden entirely when the YAML is missing, empty, or no widget passes its checks.

## Renderer

`noerd::layout.dashboard-widgets` (`resources/views/components/layout/dashboard-widgets.blade.php`)
reads the YAML in `mount()`, filters by `app`/`apps` and `policy` and renders each entry via
`livewire:dynamic-component` inside a sized wrapper. It is embedded in `noerd-apps.blade.php`
directly below the tile grid.

**Projects with a published dashboard** (`php artisan noerd:publish-home`) render their own copy of
`noerd-apps.blade.php` and will not see widgets until they either republish with
`noerd:publish-home --force` or add the one-liner themselves:

```blade
<livewire:noerd::layout.dashboard-widgets />
```

## Writing a widget

A widget is a regular (single-file) Livewire component. Place it in the owning module under
`resources/views/components/dashboard-widgets/{name}.blade.php` → component name
`{module}::dashboard-widgets.{name}`.

Wrap the view in the generic shell `x-noerd::dashboard-widget` — it renders the card chrome
(border, rounded corners), an optional header and a body that scrolls internally so the widget never
grows past its declared tile height:

| Prop / slot | Description |
|-------------|-------------|
| `title` | Simple header: label on the left |
| `count` | Simple header: bold figure on the right |
| `header` slot | Full custom header (replaces `title`/`count`), e.g. a clickable button |
| default slot | Widget body (scrolls when too tall) |

Attributes pass through to the shell, so `wire:poll` can sit directly on the tag. Re-check the app
access inside the component (`AccessHelper::canUseApp()`, like the quick menu buttons do) so it
degrades gracefully when the YAML `app:` key is edited away:

```blade
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
} ?>

<x-noerd::dashboard-widget wire:poll.30s="refreshLowStock"
    :title="__('Low Stock')" :count="$lowStockCount">
    {{-- body --}}
</x-noerd::dashboard-widget>
```

Open targets from a widget exactly like everywhere else: an addressable record via
`$modalRoute('{app}.{entity}.detail', {modelId: …}, null, null, null, {fallbackComponent: '…'})`,
a narrowed/full list via `$modalRoute('…', {…}, null, null, null, {rewriteUrl: false,
fallbackComponent: '…'})` — see [Modal System](modal.md).

## Registering at install time

Module install commands add their widget with the shared trait helper (sibling of
`ensureQuickMenuButton()`):

```php
$this->ensureDashboardWidget([
    'app' => 'INVENTORY',
    'component' => 'inventory::dashboard-widgets.low-stock',
    'width' => 2,
    'height' => 2,
]);
```

`ensureDashboardWidget(array $widget, array $legacyComponents = [])` (in
`Noerd\Traits\HasModuleInstallation`) creates the YAML when missing, rewrites entries still pointing
at one of the `$legacyComponents` names (a renamed widget component), and **appends** the widget if
absent. It matches on `component` only, so an installation may re-tune `width`/`height` without a
re-run duplicating or resetting the entry. When the installer declares `app`/`apps`, an existing
entry's access keys (`policy`, `app`, `apps`) are replaced by them.
