# Page View (NoerdPage)

A `*-page` component hosts everything AROUND a record's form: the page chrome (header, footer,
tabs), the Relation Box, the widget sidebar and — optionally — an embedded slim `*-detail`
component that renders the form itself. The counterpart trait to `NoerdList`/`NoerdDetail` is
`NoerdPage`.

`NoerdDetail` composes `NoerdPage` (`use NoerdPage`), so a detail is "a page plus the model-form
concerns". The split:

| Concern | Trait | Component |
|---------|-------|-----------|
| Chrome, tabs, modal lifecycle, quick-create, list interplay | `NoerdPage` | `{entity}-page.blade.php` |
| Form fields, validation, persistence of ONE model | `NoerdDetail` | `{entity}-detail.blade.php` |

**Tenant-singleton settings screens are neither pages nor details** — they use the
`NoerdSettingsPage` trait and a `settings/{name}.yml`, see [Settings Pages](settings-page.md).

**Details are pure model forms.** Their YAML (`details/{entity}-detail.yml`, mandatory) contains
only `title`, `description`, `theme`, `quickCreate`, `tabs`, `fields`, `actions` and `lists`
(see [Detail Properties](detail-view.md#detail-properties)). `widgets:` and `relations:` do NOT
belong in a detail YAML — they are page concerns. A detail opened standalone (e.g. from a relation
field) therefore renders just the form, without widgets or relation box.

## The optional page YAML

A page MAY ship a YAML at `app-configs/{app}/pages/{entity}-page.yml` (module copy at
`app-modules/{module}/app-configs/{app}/pages/`; both copies must be kept in sync). A missing page
YAML is a normal state — hand-built pages define their layout in the component itself
(`StaticConfigHelper::getPageFields()` is silent on miss).

```yaml
title: Warehouse
detail: inventory::warehouse-detail
quickCreate: true
relations:
  - label: Items
    heroicon: cube
    relation: items
    route: inventory.items
    component: inventory::items-list
    arguments:
      warehouseId: $modelId
widgets:
  - title: Stock Movements
    route: inventory.stock-movements
    component: inventory::stock-movements-list
    columns:
      - item_name
      - quantity
    arguments:
      warehouseId: $modelId
```

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `detail` | The embedded detail Livewire component (full name, e.g. `inventory::warehouse-detail`). Drives the generic store roundtrip |
| `quickCreate` | Opt-in for the narrow quick-create modal on new records (also sizes the modal via noerd-modal) |
| `tabs` | Page-level tabs (e.g. Media, Activity Log) — rendered by the page blade via `<x-noerd::tabs>`; same properties as [detail tabs](detail-view.md#tab-properties) |
| `relations` | Relation Box tiles (see [Relation Box](#relation-box) below). Each tile may carry `route:` next to `component:` |
| `widgets` | Right-hand widget sidebar rendered by `<x-noerd::detail-grid>` / `<x-noerd::detail-widgets>` (see [Widgets](#widgets) below) |

Both `relations` and `widgets` open a list NARROWED by the current record, so their
`route:` resolves the component WITHOUT rewriting the browser URL — see
[Modal System](modal.md#route-modals).

### Widgets

`<x-noerd::detail-widgets :layout="$pageLayout" :modelId="$modelId" />` (rendered by
`<x-noerd::detail-grid>` as the right-hand column) mounts every `widgets` entry as a
`<x-noerd::detail-widget>`: a bordered card with the embedded list in **minimal** mode showing only
the named columns, plus a "Show more" link that re-opens the same list as a full modal with the
identical arguments. Nothing renders until the record is saved (`$modelId` is set).

| Property | Description |
|----------|-------------|
| `title` | (optional) Card heading (translation key) |
| `component` | The list Livewire component embedded in the card (e.g. `inventory::stock-movements-list`) — also the "Show more" fallback when `route` is not registered |
| `route` | (optional) Named list route the "Show more" link opens as a modal (URL not rewritten) |
| `columns` | Column `field` names of the list YAML shown in the card (the list's `minimalColumns`) |
| `arguments` | Arguments passed to the list — for the card and the "Show more" modal alike; the `$modelId` token resolves to the current record id, static values pass through unchanged |

### Quick-Create Lifecycle

Quick-create mode (`quickCreate: true` in the page or detail YAML, or `quickCreate` passed as a
mount argument) opens the new record as a narrow modal showing only the required fields (and
fields flagged `quickCreate: true`). Exiting the mode is a framework default: a global Livewire hook watches
every component using `NoerdPage` — as soon as an action leaves the component with a `modelId`
(i.e. the record was saved), the hook clears the quick-create flag and resizes the modal to the
full detail. Components never need to reset `quickCreate` themselves.

## Component structure

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdPage;
use Vendor\Inventory\Models\Warehouse;

new class extends Component {
    use NoerdPage;

    public ?string $detailPrimary = 'warehouseId';

    public $detailModel = Warehouse::class;
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Warehouse') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::detail-relations :layout="$pageLayout" :modelId="$modelId"
                               :modelClass="\Vendor\Inventory\Models\Warehouse::class"/>

    <x-noerd::detail-grid :layout="$pageLayout" :modelId="$modelId">
        @livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
    </x-noerd::detail-grid>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
```

- `initPage()` (protected; the trait's `mount()` default) loads the record into `$detailData`
  when `$detailModel` is set, loads the optional page YAML into `$pageLayout` and resolves
  quick-create. A custom `mount()` calls `$this->initPage()` first.
- The embedded detail is mounted with `embedded: true` — `x-noerd::page` then renders the detail
  chrome-less (no header/footer/scroll wrapper), the page owns all chrome.
- The list refreshed when the page closes is derived from the component name with its namespace
  kept (`inventory::warehouse-page` → `inventory::warehouses-list`; `getListComponent()` strips
  `-page` and `-detail` alike). A list that does not follow the plural convention overrides
  `protected function getListComponent(): string` and returns the list's component name.
- `$detailPrimary` binds `$modelId` (`int|string|null`) to the page's URL parameter
  (`?warehouseId=5`) via the trait's `queryStringNoerdPage()` — never redeclare `$modelId` or add
  a `#[Url]` attribute. The embedded detail may declare the SAME alias (it does, for standalone
  use): an `embedded: true` instance skips the binding automatically, so page and detail never
  compete for the URL parameter.
- `openRelationDetail($detailComponent, $fieldName, $detailRoute)` opens the record behind a
  `detailData` foreign key as a modal (route first, component as fallback); `setPreselect()` /
  `preselect()` hand a preselected filter value between a page and a related list through the
  session (see [Detail View](detail-view.md#custom-store--delete-logic)).

## Relation Box

A Relation Box renders a grid of clickable tiles (6 per row), each showing a heroicon, a label and the related record count, e.g. `Contacts (5)`. Clicking a tile opens the related list component as a modal, filtered by the current record. Use it instead of relation tabs when you want an overview of all relations at a glance.

It is rendered via the generic `<x-noerd::detail-relations>` component, a thin wrapper around the `<livewire:noerd::relation-box>` Livewire component. The box only renders when `modelId`, `modelClass` and at least one tile (a YAML `relations` entry or a registry contribution, see below) are present, and refreshes its counts automatically when a list modal closes (`#[On('closeTopModal')]`).

Besides the page YAML, an optional module can contribute tiles programmatically via the `RelationBoxRegistry` — e.g. an invoicing module appends an Invoices tile to the customer page without the customer module knowing about it. Contributed tiles render after the YAML tiles; see [extension-registries.md](extension-registries.md#relationboxregistry).

### Blade Usage

Place the component between the header slot and the page body:

```blade
<x-noerd::detail-relations
    :layout="$pageLayout"
    :modelId="$modelId"
    :modelClass="\Vendor\Inventory\Models\Warehouse::class" />
```

| Prop | Description |
|------|-------------|
| `layout` | The page's `$pageLayout` (provides the `relations` array) |
| `modelId` | The current record id; tiles are hidden when empty |
| `modelClass` | Fully-qualified Eloquent model class used to load the record and count relations |

### YAML Configuration

```yaml
title: Warehouse
detail: inventory::warehouse-detail
relations:
  - label: Sub-Warehouses
    heroicon: building-office-2
    relation: children
    component: inventory::warehouses-list
    arguments:
      parentWarehouseId: $modelId
  - label: Items
    heroicon: cube
    relation: items
    component: inventory::items-list
    arguments:
      warehouseId: $modelId
```

### Relation Properties

| Property | Description |
|----------|-------------|
| `label` | Tile label (translation key) |
| `heroicon` | Heroicon rendered before the label |
| `relation` | Eloquent relationship method on the model used to count records (e.g. `items`). An unknown method yields a count of `0` instead of throwing |
| `route` | Named route of the list, opened as a modal. The browser URL is deliberately NOT rewritten — the tile opens the list NARROWED by the current record, which a plain list route cannot express |
| `component` | List component opened as a modal on click (e.g. `inventory::items-list`) — also the fallback when `route` is not registered |
| `arguments` | Arguments passed to the modal; the `$modelId` token resolves to the current record id, static values pass through unchanged |

## Generic store roundtrip

The save flow between page and embedded detail is fully generic — no per-component events:

1. The page footer's Save calls `NoerdPage::store()`, which dispatches
   **`storeDetail-{detail}`** (suffix = the full component name from the YAML `detail:` key).
2. The detail listens (via `getListeners()`), runs its normal `store()` — identical to a
   standalone save — and ends in `finishStore($model)`.
3. `finishStore()` (protected) dispatches **`detailStored-{detail}`** with the model id. The page
   adopts the id (`embeddedDetailStored()`), refreshes its `$detailData` snapshot (merge —
   page-owned keys survive), runs `storeProcess()` and finally the protected hook
   **`afterEmbeddedDetailStored($model)`**.
4. Pages that persist page-owned state (e.g. variant groups, uploads) override
   `afterEmbeddedDetailStored(Model $model)`.

Live form sync: an embedded detail mirrors its form state via **`detailDataUpdated-{detail}`**
(`NoerdDetail::updatedDetailData()` → `syncPayload()`, override the latter to filter the payload).
The page merges it in `embeddedDetailDataUpdated()` (override to add side effects, e.g. a change
counter for a live preview).

## References

- `resources/views/components/noerd-user-page.blade.php` + `app-configs/setup/pages/noerd-user-page.yml`
  and the embedded `noerd-user-detail` — the shipped page/detail pair in the setup app
- The `page.blade.stub` rendered by `noerd:make-resource` (`src/Commands/stubs/resource/`) —
  the minimal skeleton
- Trait mechanics: `tests/Feature/NoerdPageTraitTest.php`, `tests/Feature/UserPageTest.php`
