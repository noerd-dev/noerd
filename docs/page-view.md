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

**Details are pure model forms.** Their YAML (`details/{entity}-detail.yml`, mandatory) contains
only `title`, `description`, `theme`, `quickCreate` and `fields` (plus form-level `tabs` when
the fields themselves are tabbed). `widgets:` and `relations:` do NOT belong in a detail YAML —
they are page concerns. A detail opened standalone (e.g. from a relation field) therefore renders
just the form, without widgets or relation box.

## The optional page YAML

A page MAY ship a YAML at `app-configs/{app}/pages/{entity}-page.yml` (module copy at
`app-modules/{module}/app-configs/{app}/pages/`; both copies must be kept in sync). A missing page
YAML is a normal state — hand-built pages define their layout in the component itself
(`StaticConfigHelper::getPageFields()` is silent on miss).

```yaml
title: Account
detail: crm::account-detail
quickCreate: true
relations:
  - label: Contacts
    heroicon: users
    relation: contacts
    route: crm.contacts
    component: contacts-list
    arguments:
      accountId: $modelId
widgets:
  - title: Opportunities
    route: crm.opportunities
    component: crm::opportunities-list
    columns:
      - name
      - amount
    arguments:
      accountId: $modelId
```

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `detail` | The embedded detail Livewire component (full name, e.g. `crm::account-detail`). Drives the generic store roundtrip |
| `quickCreate` | Opt-in for the narrow quick-create modal on new records (also sizes the modal via noerd-modal) |
| `tabs` | Page-level tabs (e.g. Media, Activity Log) — rendered by the page blade via `<x-noerd::tabs>` |
| `relations` | Relation Box tiles (see [Relation Box](#relation-box) below). Each tile may carry `route:` next to `component:` |
| `widgets` | Right-hand widget sidebar rendered by `<x-noerd::detail-grid>` / `<x-noerd::detail-widgets>`. `route:` is the "Show more" target, `component:` the embedded list and the route fallback |

Both `relations` and `widgets` open a list NARROWED by the current record, so their
`route:` resolves the component WITHOUT rewriting the browser URL — see
[Modal System](modal.md#route-modals).

### Quick-Create Lifecycle

Quick-create mode (`quickCreate: true`, or `quickCreate` passed as a mount argument) opens the new
record as a narrow modal. Exiting the mode is a framework default: a global Livewire hook watches
every component using `NoerdPage` — as soon as an action leaves the component with a `modelId`
(i.e. the record was saved), the hook clears the quick-create flag and resizes the modal to the
full detail. Components never need to reset `quickCreate` themselves.

## Component structure

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdPage;
use Noerd\Crm\Models\Account;

new class extends Component {
    use NoerdPage;

    public ?string $detailPrimary = 'accountId';

    public $detailModel = Account::class;
    public const LIST_COMPONENT = 'crm::accounts-list';
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Account') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::detail-relations :layout="$pageLayout" :modelId="$modelId"
                               :modelClass="\Noerd\Crm\Models\Account::class"/>

    <x-noerd::detail-grid :layout="$pageLayout" :modelId="$modelId">
        @livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
    </x-noerd::detail-grid>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
```

- `initPage()` (the trait's `mount()` default) loads the record into `$detailData` when
  `$detailModel` is set, loads the optional page YAML into `$pageLayout` and resolves quick-create.
- The embedded detail is mounted with `embedded: true` — `x-noerd::page` then renders the detail
  chrome-less (no header/footer/scroll wrapper), the page owns all chrome.
- `LIST_COMPONENT` is only needed for namespaced lists; a same-namespace `account-page` derives
  `accounts-list` automatically (`getListComponent()` strips `-page` and `-detail` alike).
- `$detailPrimary` binds `$modelId` to the page's URL parameter (`?accountId=5`) via the trait's
  `queryStringNoerdPage()` — never redeclare `$modelId` or add a `#[Url]` attribute. The embedded
  detail may declare the SAME alias (it does, for standalone use): an `embedded: true` instance
  skips the binding automatically, so page and detail never compete for the URL parameter.

## Relation Box

A Relation Box renders a grid of clickable tiles (6 per row), each showing a heroicon, a label and the related record count, e.g. `Contacts (5)`. Clicking a tile opens the related list component as a modal, filtered by the current record. Use it instead of relation tabs when you want an overview of all relations at a glance.

It is rendered via the generic `<x-noerd::detail-relations>` component, a thin wrapper around the `<livewire:noerd::relation-box>` Livewire component. The box only renders when `modelId`, `modelClass` and at least one tile (a YAML `relations` entry or a registry contribution, see below) are present, and refreshes its counts automatically when a list modal closes (`#[On('closeTopModal')]`).

Besides the page YAML, an optional module can contribute tiles programmatically via the `RelationBoxRegistry` — e.g. accounting appends an Invoices tile to the party page without the party module knowing about it. Contributed tiles render after the YAML tiles; see [extension-registries.md](extension-registries.md#relationboxregistry).

### Blade Usage

Place the component between the header slot and the page body:

```blade
<x-noerd::detail-relations
    :layout="$pageLayout"
    :modelId="$modelId"
    :modelClass="\Noerd\Crm\Models\Account::class" />
```

| Prop | Description |
|------|-------------|
| `layout` | The page's `$pageLayout` (provides the `relations` array) |
| `modelId` | The current record id; tiles are hidden when empty |
| `modelClass` | Fully-qualified Eloquent model class used to load the record and count relations |

### YAML Configuration

```yaml
title: Account
detail: crm::account-detail
relations:
  - label: Sub-Accounts
    heroicon: building-office-2
    relation: children
    component: accounts-list
    arguments:
      parentAccountId: $modelId
  - label: Contacts
    heroicon: users
    relation: contacts
    component: contacts-list
    arguments:
      accountId: $modelId
```

### Relation Properties

| Property | Description |
|----------|-------------|
| `label` | Tile label (translation key) |
| `heroicon` | Heroicon rendered before the label |
| `relation` | Eloquent relationship method on the model used to count records (e.g. `contacts`). An unknown method yields a count of `0` instead of throwing |
| `route` | Named route of the list, opened as a modal. The browser URL is deliberately NOT rewritten — the tile opens the list NARROWED by the current record, which a plain list route cannot express |
| `component` | List component opened as a modal on click (e.g. `contacts-list`, without the module prefix) — also the fallback when `route` is not registered |
| `arguments` | Arguments passed to the modal; the `$modelId` token resolves to the current record id, static values pass through unchanged |

## Generic store roundtrip

The save flow between page and embedded detail is fully generic — no per-component events:

1. The page footer's Save calls `NoerdPage::store()`, which dispatches
   **`storeDetail-{detail}`** (suffix = the full component name from the YAML `detail:` key).
2. The detail listens (via `getListeners()`), runs its normal `store()` — identical to a
   standalone save — and ends in `finishStore($model)`.
3. `finishStore()` dispatches **`detailStored-{detail}`** with the model id. The page adopts the
   id, refreshes its `$detailData` snapshot (merge — page-owned keys survive), runs
   `storeProcess()` and finally the protected hook **`afterEmbeddedDetailStored($model)`**.
4. Pages that persist page-owned state (e.g. product groups/variants, uploads) override
   `afterEmbeddedDetailStored()` — see `product-page.blade.php`.

Live form sync: an embedded detail mirrors its form state via **`detailDataUpdated-{detail}`**
(`NoerdDetail::updatedDetailData()` → `syncPayload()`, override the latter to filter the payload —
see `product-detail.blade.php`). The page merges it in `embeddedDetailDataUpdated()` (override to
add side effects, e.g. a change counter for a live preview).

## References

- `app-modules/crm/resources/views/components/account-page.blade.php` + `app-configs/crm/pages/account-page.yml` — the minimal reference pair
- `app-modules/crm/resources/views/components/lead-page.blade.php` — page-level tabs, stage bar, audit tab, two-step qualify flow (`leadQualifyStore` → detail validates/saves → `leadQualifyStored`)
- `app-modules/product/resources/views/components/product-page.blade.php` — heavy `afterEmbeddedDetailStored()` (groups/variants/S3), live preview via `embeddedDetailDataUpdated()`
- Settings-style `*-page` components without a page YAML may use `NoerdDetail` directly; the `-page` suffix skips the detail-YAML lookup for them (`NoerdDetail::mountDetailComponent()`)
