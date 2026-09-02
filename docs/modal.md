# Modal System

A modal system for Livewire 4 that opens any Livewire component in a modal — no traits, no modifications to your component code.

## Installation
If you're using noerd/noerd, the noerd/modal package is already included as a dependency. To use noerd/modal standalone, install it via Composer:

```bash
composer require noerd/modal
```

The package auto-registers via Laravel's Service Provider system.

## Layout Setup

Add the modal assets to your layout's `<head>`:

```blade
<head>
    ...
    <x-noerd::noerd-modal-assets/>
    ...
</head>
```

Add the modal component at the beginning of `<body>` (before other Livewire components):

```blade
<body x-data>
    <livewire:noerd-modal::noerd-modal /> <!-- must be loaded before livewire components -->

    {{ $slot }}
</body>
```

The noerd layout (`noerd::layouts.app`) already contains both. The default panel position comes from
`config('noerd-modal.position')` (`center` or `right`, published as `config/noerd-modal.php`); every
call may override it per modal.

## Opening Modals

Two families of calls exist — by **component** (`modal` / `$modal`) and by **route**
(`modalRoute` / `$modalRoute`, see [Route Modals](#route-modals)) — each available from PHP through
the `Noerd` facade (`Noerd\Services\NoerdManager`) and from Blade through an Alpine magic.

### Signatures

PHP (`use Noerd\Facades\Noerd;` — must be called inside a Livewire request lifecycle):

| Method | Signature |
|--------|-----------|
| `Noerd::modal()` | `modal(string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $quickCreate = false): void` |
| `Noerd::modalRoute()` | `modalRoute(string $routeName, mixed $arguments = [], ?string $position = null, ?string $size = null, ?string $fallbackComponent = null, bool $rewriteUrl = true): void` |
| `Noerd::modalFor()` | `modalFor(?string $routeName, ?string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $rewriteUrl = true): void` — route when given, component otherwise |

Alpine magics (`resources/js/noerd-modal.js` of `noerd/modal`):

| Magic | Signature |
|-------|-----------|
| `$modal` | `$modal(component, args = {}, source = null, position = null, size = null)` |
| `$modalRoute` | `$modalRoute(route, args = {}, source = null, position = null, size = null, options = {})` with `options.fallbackComponent` and `options.rewriteUrl` |

| Parameter | Description |
|-----------|-------------|
| `component` / `route` | Livewire component name (`inventory::item-detail`) or named `Route::livewire()` route (`inventory.item.detail`) |
| `arguments` / `args` | Parameters bound to the component's public properties. A scalar is treated as `['modelId' => $value]` |
| `source` | Component whose `refreshList-{name}` event fires when the modal closes. PHP sets it to the current Livewire component, the magics resolve the Livewire component the clicked element belongs to — pass it only to refresh a DIFFERENT component |
| `position` | `center` or `right`; `null` = `config('noerd-modal.position')` |
| `size` | `default` or `narrow`; `null` = `default` |
| `quickCreate` | PHP only: adds `quickCreate: true` to the arguments and defaults `size` to `narrow` |
| `fallbackComponent` | Component opened when the route name is not registered (see [Fallback component](#fallback-component)) |
| `rewriteUrl` | `false` resolves the route but keeps the browser URL (see [Suppressing the URL rewrite](#suppressing-the-url-rewrite)) |

### Basic Usage

```php
use Noerd\Facades\Noerd;

// Open a component modal
Noerd::modal('inventory::stock-import-modal', ['categoryId' => $this->modelId]);

// Shorthand: a scalar argument is treated as modelId
Noerd::modal('inventory::item-detail', 123);

// Right-hand narrow panel
Noerd::modal('inventory::item-detail', ['modelId' => 123], 'right', 'narrow');
```

```blade
<!-- Open without parameters -->
<button @click="$modal('inventory::stock-import-modal')">
    Import
</button>

<!-- Open with parameters; the opening component refreshes itself on close — no source needed -->
<button @click="$modal('inventory::item-detail', { modelId: 123 })">
    Edit Item
</button>

<!-- Open with an explicit source to refresh ANOTHER component instead -->
<button @click="$modal('inventory::item-detail', { modelId: 123 }, 'inventory::items-list')">
    Edit Item
</button>
```

Both paths dispatch the same Livewire event `noerdModal` to the modal stack component.

### Parameters in Components

Parameters are automatically bound to public properties:

```php
<?php

use Livewire\Component;

new class extends Component
{
    public ?int $modelId = null; // Set to 123 when opened with { modelId: 123 }
};
?>

<div class="p-4">
    @if($modelId)
        Editing record: {{ $modelId }}
    @else
        Creating new record
    @endif
</div>
```

## Closing Modals

### Automatic Methods

- **Escape Key**: Pressing Escape closes the topmost modal
- **Close Button**: Built-in X button in the top-right corner

### Programmatic Methods

From within a Livewire component:

```php
// Close the topmost modal
$this->dispatch('closeTopModal');
```

With the `NoerdDetail` trait (automatically refreshes the source list):

```php
// Close modal and refresh the associated list
$this->closeModalProcess('inventory::items-list');

// Close modal and auto-detect list component
$this->closeModalProcess($this->getListComponent());
```

## Event System

Events handled by the modal stack component (`noerd-modal::noerd-modal`):

| Event | Description |
|-------|-------------|
| `noerdModal` | Opens a modal — dispatched by `Noerd::modal()` / `Noerd::modalRoute()` and the `$modal` / `$modalRoute` magics. Never dispatch it by hand; the facade and the magics are the API |
| `closeTopModal` | Closes the topmost modal, restores the URL and fires `refreshList-{source}` for its source component |
| `closeAllModals` | Closes the whole stack |
| `resizeTopModal` | Changes the panel size of the topmost modal (`size: 'default'` or `'narrow'`) |

Events dispatched by the modal stack:

| Event | Description |
|-------|-------------|
| `modal-closed-global` | Fired when the last open modal has closed |
| `refreshList-{component}` | Refreshes the source component (`NoerdList::refreshList()` / `NoerdPage`) — the component name after the last dot |

## Route Modals

A modal can also be opened by the NAME of a `Route::livewire()` route instead of by
component name. The route is resolved to the component behind it, the browser URL is
rewritten to the route (plus `?modal=true`) and restored when the modal closes.

```php
use Noerd\Facades\Noerd;

// Opens the component behind the route and rewrites the URL to /inventory/item/5?modal=true
Noerd::modalRoute('inventory.item.detail', ['modelId' => 5]);
```

```blade
<button @click="$modalRoute('inventory.item.detail', { modelId: 5 })">Edit Item</button>
```

Route params are filled by name from the arguments — `modelId` by convention, but any
property the target binds may be the record's identity (`/setup/object-manager/{table}`).
A create modal has no record id — the conventional `{modelId}` param then carries the
`new` sentinel (`/inventory/item/new?modal=true`), which `NoerdPage::prepareRoutedModal()`
maps back to `null`. Reloading such a URL reopens the record as a modal over the page the
user last visited (`RoutedModal::redirectToRoutedModal()`, shared by `NoerdPage` and
`NoerdList`).

### Route modal or component modal?

**Use a route** when the modal shows ONE addressable record. All three must hold:

1. the target uses `NoerdDetail` / `NoerdPage` (a `*-detail` / `*-page`) or — for a
   record that IS a list, e.g. the object of the custom-attribute manager —
   `NoerdList`. Those traits share the `?modal=true` reload contract through
   `RoutedModal`; `NoerdPage` additionally understands the `new` sentinel;
2. a named `Route::livewire('{app}/{entity}/{modelId}', …)` route exists for exactly
   that component;
3. every identity-bearing argument is a parameter of that route — conventionally
   `modelId`, but any bound property works (`/setup/object-manager/{table}`).
   Everything else is chrome (`relations`, `quickCreate`).

You get a shareable URL, a working reload, "open in new tab", and callers that no
longer hard-code a foreign module's component name.

**Keep the component** for everything else:

- **Action dialogs** — `*-modal`, `*-confirmation`, `*-review`, `*-import`, `*-editor`:
  they perform one operation and close, they have no identity.
- **Pickers** — anything opened with `listActionMethod`, `selectMode`, `selectContext`,
  `multiSelect`, `returnsSelection` or `context`. The selection is not a URL.
- **Filtered lists** — a list narrowed by a parent record (`categoryId`, `folderId`).
  A route may be used here for decoupling, but the URL is deliberately NOT rewritten
  (see below).

Reviewer's test: *paste the resulting URL into a fresh tab — does it show the same
thing?* Yes → route. No → component.

### Fallback component

Pass the component alongside the route and it opens when the route name is not
registered — so a caller may reference a route owned by an optional module:

```php
Noerd::modalFor('inventory.item.detail', 'inventory::item-detail', ['modelId' => 5]);
```

`modalFor()` is the canonical shape: route when configured and registered, component
otherwise. In Blade the same is expressed through the `$modalRoute` options object:

```blade
@click="$modalRoute('inventory.item.detail', { modelId: 5 }, null, null, null, { fallbackComponent: 'inventory::item-detail' })"
```

### Suppressing the URL rewrite

`rewriteUrl: false` resolves the route to its component but keeps the browser URL. Use
it for targets that are not addressable — most notably a list opened filtered by a
parent record, where the list route carries no filter param and a reload would show
the unfiltered list:

```blade
@click="$modalRoute('inventory.items', { categoryId: 5 }, null, null, null, { rewriteUrl: false })"
```

The modal stack also guards this automatically: an argument that is neither a route
param nor pure chrome suppresses the rewrite, so a YAML author cannot produce a URL
that lies.

### Where `route:` is available

| Place | Key |
|-------|-----|
| List row click | `public ?string $detailRoute` on the list component |
| List header action (`lists/*.yml`) | `route:` (+ optional `arguments:`) instead of `action:` |
| Detail action (`details/*.yml`) | `route:` instead of `modalComponent:` |
| Relation box tile (`pages/*.yml` `relations:`) | `route:` next to `component:` (no URL rewrite) |
| Widget "show more" (`pages/*.yml` `widgets:`) | `route:` next to `component:` (no URL rewrite) |
| List column `type: relation_link` | `route:` instead of `modalComponent:` |
| Relation field | `detailRoute:` on `RelationFieldDefinition::model()`, or `detailRoute:` on the field |
| Sidebar entry (`navigation.yml`) | `modalRoute:` to open as a modal, `newRoute:` for the "+" button — `route:` keeps meaning "navigate" |
| Page/detail tab | `modalRoute:` next to `component:` |
| Dashboard card | `route=` instead of `component=` |

## Modal Stacking

The modal system supports unlimited nested modals:

- Each modal gets a unique key and iteration number
- Only the topmost modal responds to Escape key
- Z-index is managed automatically
- Closing a modal reveals the one beneath

Example flow:
1. Open `items-list` → Click row
2. Opens `item-detail` (modal 1)
3. Click "Add Supplier" → Opens `supplier-detail` (modal 2)
4. Press Escape → Closes modal 2, modal 1 remains

## Fullscreen Mode

- Toggle button in the top-right corner (desktop only)
- State persists via session (`modal_fullscreen`)
- Applies to all modals during the session

The toggle is applied **client-side** via the Alpine store `$store.app.modalFullscreen`; the
Livewire call only persists the preference and calls `skipRender()`. The panel is permanently
centered (`top-1/2` + `-translate-y-1/2`); the two states differ only in `min-height`/`max-height`
(fullscreen: `100dvh`, covering the whole viewport), `max-width` and `border-radius` — all
interpolable — so the panel grows symmetrically from the center instead of jumping. Consequently:

- Never bake the fullscreen state into server-rendered classes. Bind it with Alpine (`:class`)
  instead, otherwise the layout snaps once more when the response arrives
- A component with `public bool $forceModalFullscreen = true;` neither reads nor writes the shared
  preference — its panel renders the fullscreen geometry unconditionally
- `x-noerd::page` reads the flag from the panel scope (`modalFullscreen`), so its `max-height`
  animates in lockstep with the panel

