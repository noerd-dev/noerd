# Dashboard Widgets

The apps dashboard (`noerd-apps`) can render a row of **widgets** below the tenant-app tile grid —
e.g. a card listing the open tables of a restaurant, sized as wide and tall as two app tiles.
Configuration mirrors the [quick-menu](quick-menu.md): a single project-level YAML file that modules
write themselves into at install time.

## Configuration

File: `app-configs/dashboard-widgets.yml` (project level only — like `quick-menu.yml` there is no
module copy and no `StaticConfigHelper` involvement).

```yaml
widgets:
  - policy: canSupplat
    component: 'supplat::dashboard-widgets.open-tables'
    width: 2
    height: 2
```

| Key | Description |
|-----|-------------|
| `app` / `apps` | Tenant app name (string) or list of names (`tenant_apps.name`) the widget belongs to. The widget renders only when at least ONE of them is assigned to the selected tenant AND the app permission allows it (`AccessHelper::canUseApp()`). Set it on every widget that shows an app's data — this replaces the removed per-module tenant gates (`canSupplat` & Co.). |
| `policy` | (optional) Gate ability or policy name — only for checks beyond app usability. Checked like the quick-menu: `Gate::define`d abilities first, then policies on the `Tenant` model. |
| `component` | Livewire component to render — bare (`quick-menu.open-orders` style) or namespaced (`supplat::dashboard-widgets.open-tables`). |
| `width` | (optional) Width in app-tile units, default `1`. |
| `height` | (optional) Height in app-tile units, default `1`. |

### Tile-unit sizing

App tiles are `9rem` (`w-36`) with a `1.5rem` gutter (`mr-6`/`mt-6`). A widget spanning `n` units
measures `n * 9rem + (n - 1) * 1.5rem`, so a `width: 2` widget aligns exactly with two app tiles
(`19.5rem`). The renderer emits the size as an inline `style` (the values come from YAML at runtime,
so Tailwind's JIT can never generate classes for them).

The widget row is hidden entirely when the YAML is missing, empty, or no widget passes its policy.

## Renderer

`noerd::layout.dashboard-widgets`
(`app-modules/noerd/resources/views/components/layout/dashboard-widgets.blade.php`) reads the YAML in
`mount()`, filters by policy and renders each entry via `livewire:dynamic-component` inside a sized
wrapper. It is embedded in `noerd-apps.blade.php` directly below the tile grid.

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

Attributes pass through to the shell, so `wire:poll` can sit directly on the tag. Re-check the gate
inside the component (like the quick-menu buttons do) so it degrades gracefully when the YAML policy
is edited away:

```blade
<?php

use Livewire\Component;
use Nywerk\Supplat\Models\SupplatTableSession;

new class extends Component {
    public int $openCount = 0;

    public function mount(): void
    {
        $this->refreshOpenTables();
    }

    public function refreshOpenTables(): void
    {
        if (! auth()->user()?->can('canSupplat')) {
            return;
        }

        $this->openCount = SupplatTableSession::query()->open()->count();
    }
} ?>

<x-noerd::dashboard-widget wire:poll.30s="refreshOpenTables"
    :title="__('Open Tables')" :count="$openCount">
    {{-- body --}}
</x-noerd::dashboard-widget>
```

Open targets from a widget exactly like everywhere else: an addressable record via
`$modalRoute('{app}.{entity}.detail', {modelId: …}, null, null, null, {fallbackComponent: '…'})`,
a narrowed/full list via `$modalRoute('…', {…}, null, null, null, {rewriteUrl: false,
fallbackComponent: '…'})`. Reference implementation:
`app-modules/supplat/resources/views/components/dashboard-widgets/open-tables.blade.php`.

## Registering at install time

Module install commands add their widget with the shared trait helper (sibling of
`ensureQuickMenuButton()`):

```php
$this->ensureDashboardWidget([
    'policy' => 'canSupplat',
    'component' => 'supplat::dashboard-widgets.open-tables',
    'width' => 2,
    'height' => 2,
], $legacyComponents = []);
```

`ensureDashboardWidget()` (in `Noerd\Traits\HasModuleInstallation`) creates the YAML when missing,
rewrites entries still pointing at a `$legacyComponents` name, and **appends** the widget if absent.
It matches on `component` only, so an installation may re-tune `policy`/`width`/`height` without a
re-run duplicating or resetting the entry.
