# Create a Detail View

Detail pages display and edit individual records with forms.

![Noerd Example App](/assets/detail.png "Detail View")

## File Locations

### YAML Configuration:
```
app-configs/{app}/details/{name}-detail.yml
```

### Livewire Component:
```
app-modules/{module}/resources/views/components/⚡{name}-detail.blade.php
```

## YAML Configuration

Example: `app-configs/accounting/details/customer-detail.yml`

```yaml
title: Customer Details
description: ''
tabs:
  - number: 1
    label: Master Data
  - label: Invoices
    component: invoices-list
    arguments:
      customerId: $customerId
    requiresId: true
fields:
  - name: detailData.name
    label: Name
    type: text
    required: true
  - name: detailData.company_name
    label: Company Name
    type: text
  - name: detailData.email
    label: Email
    type: text
  - name: detailData.phone
    label: Phone
    type: text
  - name: detailData.address
    label: Address
    type: text
  - name: detailData.zipcode
    label: Zip Code
    type: text
  - name: detailData.city
    label: City
    type: text
```

## Detail Properties

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `description` | Optional description text |
| `theme` | Theme for the form: `default`, `compact`, `numbered` or any discovered theme (see [Themes](themes.md)) |
| `tabs` | Array of tab definitions |
| `fields` | Array of form field definitions |
| `actions` | Array of action button definitions rendered above the form (see [Detail Actions](#detail-actions)) |
| `footerComponents` | Array of Livewire components rendered in the footer bar |

> **Note:** `relations:` (Relation Box) and `widgets:` are PAGE concerns — they live in the
> optional page YAML (`pages/{entity}-page.yml`), not in a detail YAML. See [Page View](page-view.md).
> A detail YAML contains only the form: `title`, `description`, `theme`, `quickCreate`, `fields`
> (and form-level `tabs`).

## Tab Properties

| Property | Description |
|----------|-------------|
| `number` | Tab index (1-based) |
| `label` | Tab label (translation key) |
| `component` | Embedded Livewire component |
| `arguments` | Arguments passed to embedded component |
| `requiresId` | Only show tab when editing existing record |

## Field Properties

| Property | Description |
|----------|-------------|
| `name` | Property path (e.g., `detailData.name`) |
| `label` | Field label (translation key) |
| `type` | Field type (text, textarea, checkbox, relation, etc.) |
| `required` | Mark field as required |
| `colspan` | Grid column span (1-12) |
| `tab` | Tab number (defaults to 1) |
| `theme` | Per-field theme override (see [Themes](themes.md)) |
| `number` | Explicit row number in the `numbered` theme (defaults to auto-increment) |

## Themes

A detail form renders in one of several **themes**, selected by the top-level `theme:` key in the
detail YAML (per-field and nested-block overrides are supported), or system-wide under
**Setup → System Settings**. A theme is a self-contained folder of element templates plus a
`theme.yml` — copying the folder creates a new theme.

```yaml
title: Account
theme: compact
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.notes
    label: Notes
    type: textarea
    colspan: 12
    theme: default   # per-field override
```

See **[Themes](themes.md)** for the full reference: built-in themes, `theme.yml` keys, creating a
new theme in a project or module, element resolution, theme-aware buttons and the system-wide
default with enforcement.

## Position Tables

Documents with line items (order, quote, invoice, order confirmation, production planning) render
their **positions** as a hand-written table, not through the YAML field grid. That table still
follows the active theme — never hardcode a control class string in a module again.

Generic components, all in the noerd module:

| Component | Props | Renders |
|---|---|---|
| `<x-noerd::positions.section>` | `theme`, `title`, `description` | The white card, the standard block head and a body whose padding follows the theme |
| `<x-noerd::positions.table>` | `theme`, `columns` | `<table>` + `<thead>`; in a numbering theme a leading `#` column is prepended |
| `<x-noerd::positions.row>` | `theme`, `number`, `colspan`, `details` slot | A full `<tbody>` (so it can be a row component's root); banded with a leading number cell in a numbering theme |
| `<x-noerd::positions.cell>` | `theme`, `width` | One `<td>` with the theme's padding |
| `<x-noerd::positions.totals>` | `theme`, `net`, `gross`, `taxes`, `currency`, `locale` | Total Net / one row per tax rate / Total Gross |
| `<x-noerd::forms.control>` | `theme`, `type` | A bare `<input>`/`<select>` styled by the theme; every `wire:*`/`step`/`disabled` attribute passes through |

`columns` entries are either a plain label or `['label' => …, 'class' => 'w-32']`; an empty label
marks the trailing action column. Labels are translated with `__()`.

`taxes` accepts both shapes in use across the modules — a `rate => amount` map (`['19' => 4.2]`) and
a list of rows (`[['tax_rate' => 19, 'tax_total' => 4.2]]`) — so a caller passes `$model->taxes`
unchanged.

**Parent detail/page blade** — read the theme once from the trait and hand it down:

```blade
@php $positionsTheme = $this->detailTheme(); @endphp

<x-noerd::positions.section :theme="$positionsTheme" title="Positions">
    <x-noerd::positions.table
        :theme="$positionsTheme"
        :columns="[['label' => 'Quantity', 'class' => 'w-32'], 'Name', '']"
    >
        @foreach($model->positions as $position)
            <livewire:module::position
                :key="$position->id"
                :$position
                :theme="$positionsTheme"
                :number="$loop->iteration"
            />
        @endforeach
    </x-noerd::positions.table>

    <x-noerd::positions.totals
        :theme="$positionsTheme"
        :net="$model->total_net"
        :gross="$model->total_gross"
        :taxes="$model->taxes"
    />
</x-noerd::positions.section>
```

**Row component** — accepts the theme and its row number as props (never call `detailTheme()` here:
a row component has no page layout of its own):

```blade
public string $theme = 'default';
public ?int $number = null;

public function mount($position, string $theme = 'default', ?int $number = null): void { … }
```

```blade
<x-noerd::positions.row :theme="$theme" :number="$number" :colspan="3">
    <x-noerd::positions.cell :theme="$theme" width="w-32">
        <x-noerd::forms.control :theme="$theme" type="number" wire:change="store" wire:model="quantity"/>
    </x-noerd::positions.cell>
    …
    <x-slot:details>
        {{-- optional full-width row beneath, e.g. a rich-text description --}}
    </x-slot:details>
</x-noerd::positions.row>
```

`colspan` is the number of columns declared on the table; `positions.row` adds the number column
itself when the theme numbers rows, so the details row never has to be adjusted per theme.

**`$this->detailTheme()`** lives on the `NoerdPage` trait (and therefore on `NoerdDetail`). It
normalizes `$pageLayout` — an unregistered theme falls back to `default`.

In the `numbered` theme a position table gets a leading `#` column and gray banded rows, matching
the numbered form rows above it.

Note that `controlClasses` describes the control *inside a position row*; the element templates in
the theme folders (`themes/{name}/`) keep their own (slightly smaller) class strings.

## Detail Actions

Action buttons render a row above the form via the generic `<x-noerd::detail-actions>` component. Each button calls a Livewire method on the detail component itself. Use this for record-level operations such as "Transfer to Account" or "Generate PDF".

### Blade Usage

Place the component between the header slot and `<x-noerd::tab-content>`:

```blade
<x-noerd::detail-actions :layout="$pageLayout" :modelId="$modelId" />
```

### YAML Configuration

```yaml
title: Lead
actions:
  - label: Transfer to Account
    action: transferToAccount
    heroicon: arrows-right-left
    confirm: Transfer this lead to a new account?
fields:
  - name: detailData.name
    label: Name
    type: text
```

### Action Properties

| Property | Description |
|----------|-------------|
| `label` | Button label (translation key) |
| `route` | Named `Route::livewire()` route opened as a modal (preferred for record targets) |
| `modalComponent` | Livewire component opened as a modal — also the fallback when `route` is not registered |
| `url` | Renders the action as a plain link (`<a href>`) instead of a button — either a literal URL (`http…` / `/…`) or a key in the `urls` map passed to the component |
| `newTab` | Only with `url:` — defaults to `true` (`target="_blank"`). Set to `false` to open in the same tab |
| `action` | Livewire method called via `wire:click` (used when neither `route`, `modalComponent` nor `url` is set) |
| `heroicon` | Optional heroicon rendered before the label |
| `confirm` | Optional confirmation prompt shown via `wire:confirm` (translation key) |
| `requiresId` | Defaults to `true` — the button is hidden until the record is saved (`modelId` is set). Set to `false` to always show it |
| `viewExists` | Optional view name — the button is hidden when that view is not registered, so YAML may reference an optional module safely |

Precedence is `route:` → `modalComponent:` → `url:` → `action:`. A `route:` action whose route is
not registered and that has no `modalComponent` is not rendered at all. See
[Modal System](modal.md#route-modals) for when a route is the right target.

```yaml
actions:
  - label: Open Account
    route: crm.account.detail
    modalComponent: crm::account-page   # fallback if the CRM module is absent
    heroicon: building-office
    arguments:
      modelId: $modelId
```

### Link Actions

A `url:` action renders as a link that opens in a new tab — use it for targets outside the backend
(a public guest page, an external system). A record-dependent URL is computed by the detail component
and handed to the component through the `urls` prop; the YAML only names the key:

```blade
<x-noerd::detail-actions :layout="$pageLayout" :modelId="$modelId"
                         :urls="['tableUrl' => $this->tableUrl()]" />
```

```yaml
actions:
  - label: Open Table
    url: tableUrl
    heroicon: arrow-top-right-on-square
```

An action whose `url:` neither is a literal URL nor resolves through the `urls` map is not rendered
at all, so YAML may reference a URL an installation does not provide.

### Livewire Method

Define a public method matching each `action` on the detail component:

```php
public function transferToAccount(): void
{
    // validation / business logic
}
```

## Relation Box

A Relation Box renders a grid of clickable tiles (6 per row), each showing a heroicon, a label and the related record count, e.g. `Contacts (5)`. Clicking a tile opens the related list component as a modal, filtered by the current record. Use it instead of relation tabs when you want an overview of all relations at a glance.

> The `relations:` array is declared in the PAGE YAML (`pages/{entity}-page.yml`) and the
> component is placed in the `*-page` blade — see [Page View](page-view.md). The component
> reference below applies unchanged.

It is rendered via the generic `<x-noerd::detail-relations>` component, a thin wrapper around the `<livewire:noerd::relation-box>` Livewire component. The box only renders when `modelId`, a non-empty `relations` array and `modelClass` are all present, and refreshes its counts automatically when a list modal closes (`#[On('closeTopModal')]`).

### Blade Usage

Place the component between the header slot and `<x-noerd::tab-content>`:

```blade
<x-noerd::detail-relations
    :layout="$pageLayout"
    :modelId="$modelId"
    :modelClass="\Noerd\Crm\Models\Account::class" />
```

| Prop | Description |
|------|-------------|
| `layout` | The detail's `$pageLayout` (provides the `relations` array) |
| `modelId` | The current record id; tiles are hidden when empty |
| `modelClass` | Fully-qualified Eloquent model class used to load the record and count relations |

### YAML Configuration

```yaml
title: Account
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
fields:
  - name: detailData.name
    label: Name
    type: text
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

## Embedded Lists

Render one or more **compact lists** below the form — e.g. the Opportunities of an Account, or one
parts list per assembly on a vehicle. Each list renders a section heading (styled like the detail
block title) and the referenced list component in its
[compact](list-view.md#compact-mode-embedded-lists), full-width variant — `compact` and
`disableModal` are applied automatically. There are two ways to use it:

- **YAML-driven** — `<x-noerd::detail-lists>` (plural) for a fixed set of lists declared in the YAML.
- **Blade-direct** — `<x-noerd::detail-list>` (singular) for dynamic cases (e.g. a `@foreach` loop)
  where the number of lists depends on data and cannot be expressed in YAML.

`<x-noerd::detail-lists>` simply loops the YAML `lists` array and delegates each entry to
`<x-noerd::detail-list>`, so both share the same rendering.

### YAML-driven: `<x-noerd::detail-lists>`

The list counterpart to `<x-noerd::tab-content>`: a single line in the Blade, fully driven by a
`lists` array in the YAML. Place it after `<x-noerd::tab-content>`:

```blade
<x-noerd::detail-lists :layout="$pageLayout" :modelId="$modelId" />
```

```yaml
lists:
  - title: Opportunities
    component: crm::opportunities-list
    arguments:
      accountId: $modelId
```

| Property | Description |
|----------|-------------|
| `title` | (optional) Section heading above the list (translation key), rendered via `detail.block-head` |
| `description` | (optional) Sub-heading text (translation key) |
| `component` | The list Livewire component to embed (e.g. `crm::opportunities-list`) |
| `arguments` | Arguments passed to the list; the `$modelId` token resolves to the current record id, static values pass through unchanged |
| `lazy` | (optional) Lazy-load the list |

Nothing is rendered until the record is saved (`$modelId` is set) or when `lists` is empty.

### Blade-direct: `<x-noerd::detail-list>`

For dynamic cases that YAML cannot express — e.g. rendering one list **per related record** in a loop.
Pass the values directly as props:

```blade
@foreach ($vehicle->assemblies as $assembly)
    <x-noerd::detail-list
        component="pdm::parts-list"
        :arguments="['assemblyId' => $assembly->id]"
        lazy
        :title="$assembly->name"
        :wireKey="$assembly->id . '-parts'" />
@endforeach
```

| Prop | Description |
|------|-------------|
| `component` | The list Livewire component to embed (e.g. `pdm::parts-list`) |
| `arguments` | Array of mount params for the list (real values — no `$modelId` token resolution here) |
| `title` | (optional) Section heading (translation key) |
| `description` | (optional) Sub-heading text (translation key) |
| `lazy` | (optional) Lazy-load the list (passed through to Livewire via the params array) |
| `wireKey` | (optional) Explicit `wire:key`; defaults to a hash of component + arguments. Vary it (e.g. include a timestamp) to force a re-render when the underlying data changes |

The embedded list is always compact (no header, no pagination — only the first `perPage` rows), so use
it for record-scoped lists.

## Footer Components

Footer components are additional Livewire components rendered in the footer bar next to the delete and save buttons. They are defined in the YAML configuration and automatically passed to the `delete-save-bar` component.

### YAML Configuration

```yaml
footerComponents:
  - component: customer-test-button
    requiresId: false
  - component: customer-export
    requiresId: true
```

### Footer Component Properties

| Property | Description |
|----------|-------------|
| `component` | Name of the Livewire component to render |
| `requiresId` | Only render when editing an existing record (`modelId` is set). Defaults to `false` |

### Blade Usage

Pass `footerComponents` and `modelId` from the page layout to the `delete-save-bar`:

```blade
<x-slot:footer>
    <x-noerd::delete-save-bar :showDelete="isset($modelId)"
        :footerComponents="$pageLayout['footerComponents'] ?? []"
        :modelId="$modelId ?? null"/>
</x-slot:footer>
```

Each footer component receives `modelId` as a prop and is rendered via `<livewire:is>`.

## Livewire Component

A detail component declares its model as `public $detailModel` and its URL alias as
`public ?string $detailPrimary` — everything else (mounting, `store()`, `delete()`)
comes from the `NoerdDetail` trait.

`$detailPrimary` is MANDATORY for every model-backed detail (a missing declaration
throws on mount). It binds `$modelId` to the entity-scoped query parameter
(`?supplierId=5`) — never redeclare `$modelId` or add a `#[Url]` attribute yourself.
The binding is applied by the trait (`queryStringNoerdPage()`) and automatically
skipped when the component is mounted `embedded: true`, so a hosting page can own
the same URL parameter without conflicts. Set `detailPrimary` only as a literal
property default (never in `mount()`): the modal system probes a fresh instance to
collect the URL params to clear on close. Components without `$detailModel`
(dashboards, always-embedded children) simply leave it `null` — no URL binding.

Example: `supplier-detail.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdDetail;
use Noerd\Accounting\Models\Supplier;

new class extends Component {
    use NoerdDetail;

    public $detailModel = Supplier::class;

    public ?string $detailPrimary = 'supplierId';
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Supplier') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"
            :footerComponents="$pageLayout['footerComponents'] ?? []"
            :modelId="$modelId ?? null"/>
    </x-slot:footer>
</x-noerd::page>
```

The trait defaults hydrate `$detailData` from `$detailModel` on mount, validate via
`validateFromLayout()`, persist with `updateOrCreate(['id' => $modelId], $detailData)` on
`store()`, and delete + close the modal on `delete()`.

### Custom Store / Delete Logic

Only when the persistence deviates from the default, override `store()` and/or `delete()` —
always ending with the generic helpers `storeProcess($model)` / `closeModalProcess()`:

```php
new class extends Component {
    use NoerdDetail;

    public $detailModel = Customer::class;

    public function store(): void
    {
        $this->validateFromLayout();

        $customer = CustomerService::save($this->modelId, $this->detailData);

        $this->storeProcess($customer);
    }
};
```

The same applies to `mount()`: override it only for extra logic (e.g. `setPreselect()`, defaults
for new records, relation titles) and call `$this->initDetail()` first.

## Key Concepts

- **Trait:** `NoerdDetail` provides `$detailData`, `$modelId`, `$pageLayout`, and helper methods
- **$detailModel:** `public $detailModel = Model::class;` is required on every model-backed detail — it drives mounting, the default `store()`/`delete()`, and the header actions (layout/object manager)
- **Properties:** `$detailData` (array) for form binding, `$modelId` (from trait) for the record ID
- **mount() / store() / delete():** Provided by the trait — only override for custom behavior
- **validateFromLayout():** Validates against YAML-defined rules
- **$this->getListComponent():** Automatically determines the associated list component
- The Eloquent model is **never** stored as a component property
- **tenant_id:** Do not set `tenant_id` manually in `store()`. Models using the `BelongsToTenant` trait have `tenant_id` assigned automatically on creation.

## Naming Conventions

- Lists: `{plural}-list.blade.php` (e.g., `customers-list.blade.php`)
- Details: `{singular}-detail.blade.php` (e.g., `customer-detail.blade.php`)
- Components must be placed directly in the `components/` folder, not in subfolders

## Next Steps

- [Components](components.md) - Learn about available UI components
- [YAML Configuration](yaml-configuration.md) - Deep dive into YAML options
- [Creating Modules](creating-modules.md) - Build independent modules
