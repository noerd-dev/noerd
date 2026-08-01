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
    <livewire:noerd-modal/> <!-- must be loaded before livewire components -->

    {{ $slot }}
</body>
```

## Opening Modals

Use the `$modal()` Alpine magic function to open any Livewire component in a modal.

### Syntax

```
$modal(componentName, arguments?, source?)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `componentName` | string | Name of the Livewire component |
| `arguments` | object | Optional parameters passed to the component |
| `source` | string | Optional source component for list refresh |

### Basic Usage

```blade
<!-- Open without parameters -->
<button @click="$modal('customer-detail')">
    New Customer
</button>

<!-- Open with parameters -->
<button @click="$modal('customer-detail', { modelId: 123 })">
    Edit Customer
</button>

<!-- Open with source for auto-refresh -->
<button @click="$modal('customer-detail', { modelId: 123 }, 'customers-list')">
    Edit Customer
</button>
```

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
$this->closeModalProcess('customers-list');

// Close modal and auto-detect list component
$this->closeModalProcess($this->getListComponent());
```

## Event System

| Event | Description |
|-------|-------------|
| `noerdModal` | Opens a modal (dispatched by `$modal()`) |
| `closeTopModal` | Closes the topmost modal |
| `modal-closed-global` | Fired when all modals are closed |
| `refreshList-{component}` | Refreshes a specific list component |

### Dispatching Events

The recommended way to open a modal from a Livewire component is via the `Noerd` facade:

```php
use Noerd\Facades\Noerd;

// Open a modal
Noerd::modal('customer-detail', ['modelId' => 123]);

// Shorthand: a scalar argument is treated as modelId
Noerd::modal('customer-detail', 123);

// Close the topmost modal
$this->dispatch('closeTopModal');
```

The facade automatically sets `source` to the currently rendering Livewire component, so the source list is refreshed when the modal closes.

Internally this dispatches the same `noerdModal` event — direct dispatching still works if you need full control:

```php
$this->dispatch('noerdModal',
    modalComponent: 'customer-detail',
    arguments: ['modelId' => 123],
    source: 'customers-list'
);
```

## Route Modals

A modal can also be opened by the NAME of a `Route::livewire()` route instead of by
component name. The route is resolved to the component behind it, the browser URL is
rewritten to the route (plus `?modal=true`) and restored when the modal closes.

```php
use Noerd\Facades\Noerd;

// Opens the component behind the route and rewrites the URL to /crm/account/5?modal=true
Noerd::modalRoute('crm.account.detail', ['modelId' => 5]);
```

```blade
<button @click="$modalRoute('crm.account.detail', { modelId: 5 })">Edit Account</button>
```

Route params are filled by name from the arguments. A create modal has no record id —
the conventional `{modelId}` param then carries the `new` sentinel
(`/crm/account/new?modal=true`), which `NoerdPage::prepareRoutedModal()` maps back to
`null`. Reloading such a URL reopens the record as a modal over the page the user last
visited (`NoerdPage::redirectToListModal()`).

### Route modal or component modal?

**Use a route** when the modal shows ONE addressable record. All three must hold:

1. the target is a `*-detail` / `*-page` component using `NoerdDetail` / `NoerdPage` —
   only those understand the `new` sentinel and the `?modal=true` reload contract;
2. a named `Route::livewire('{app}/{entity}/{modelId}', …)` route exists for exactly
   that component;
3. the only identity-bearing argument is `modelId` — everything else is chrome
   (`relations`, `quickCreate`).

You get a shareable URL, a working reload, "open in new tab", and callers that no
longer hard-code a foreign module's component name.

**Keep the component** for everything else:

- **Action dialogs** — `*-modal`, `*-confirmation`, `*-review`, `*-import`, `*-editor`:
  they perform one operation and close, they have no identity.
- **Pickers** — anything opened with `listActionMethod`, `selectMode`, `selectContext`,
  `multiSelect`, `returnsSelection` or `context`. The selection is not a URL.
- **Filtered lists** — a list narrowed by a parent record (`accountId`, `folderId`).
  A route may be used here for decoupling, but the URL is deliberately NOT rewritten
  (see below).

Reviewer's test: *paste the resulting URL into a fresh tab — does it show the same
thing?* Yes → route. No → component.

### Fallback component

Pass the component alongside the route and it opens when the route name is not
registered — so a caller may reference a route owned by an optional module:

```php
Noerd::modalFor('customer.detail', 'customer::customer-detail', ['modelId' => 5]);
```

`modalFor()` is the canonical shape: route when configured and registered, component
otherwise. In Blade the same is expressed through the `$modalRoute` options object:

```blade
@click="$modalRoute('customer.detail', { modelId: 5 }, null, null, null, { fallbackComponent: 'customer::customer-detail' })"
```

### Suppressing the URL rewrite

`rewriteUrl: false` resolves the route to its component but keeps the browser URL. Use
it for targets that are not addressable — most notably a list opened filtered by a
parent record, where the list route carries no filter param and a reload would show
the unfiltered list:

```blade
@click="$modalRoute('crm.contacts', { accountId: 5 }, null, null, null, { rewriteUrl: false })"
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
1. Open `customers-list` → Click row
2. Opens `customer-detail` (modal 1)
3. Click "Add Address" → Opens `address-detail` (modal 2)
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

