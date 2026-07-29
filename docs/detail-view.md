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
| `view` | Layout view for the form: `default`, `compact`, `numbered` (see [Layout Views](#layout-views)) |
| `tabs` | Array of tab definitions |
| `fields` | Array of form field definitions |
| `actions` | Array of action button definitions rendered above the form (see [Detail Actions](#detail-actions)) |
| `footerComponents` | Array of Livewire components rendered in the footer bar |

> **Note:** `relations:` (Relation Box) and `widgets:` are PAGE concerns — they live in the
> optional page YAML (`pages/{entity}-page.yml`), not in a detail YAML. See [Page View](page-view.md).
> A detail YAML contains only the form: `title`, `description`, `view`, `quickCreate`, `fields`
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
| `view` | Per-field layout view override (see [Layout Views](#layout-views)) |
| `number` | Explicit row number in the `numbered` view (defaults to auto-increment) |

## Layout Views

A detail form renders in one of several **views**, selected by the top-level `view:` key in the
detail YAML. Built-in views:

| View | Layout |
|------|--------|
| `default` | Label on top of the input (also used when `view` is absent or unknown) |
| `compact` | Label to the LEFT of the input with tighter vertical spacing |
| `numbered` | Form rows like the German ELSTER tax UI: one field per full-width row (colspan is ignored), light gray row background, leading row number, right-aligned label, input on the right |

```yaml
title: Account
view: compact
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.notes
    label: Notes
    type: textarea
    colspan: 12
    view: default   # per-field override
```

The view is inherited by nested `type: block` fields; a single field (or nested block) may override
it with its own `view:` key.

**Numbered view:** rows are numbered automatically per block (nested blocks restart at 1). A field
may pin its number with an explicit `number:` key — numbers may repeat, like on tax forms. The
shared row chrome (gray row, number cell, right-aligned label) lives in
`<x-noerd::detail.numbered-row>`; the per-field partials in `forms/numbered/` only provide the bare
control.

**Backward compatibility:** the legacy boolean `compact: true` is still accepted and maps to
`view: compact`; an explicit `view:` wins. Use `view:` in all new YAML.

### Registering a New View

Views are `DetailViewDefinition` objects held by the `DetailViewRegistry` singleton. The built-ins
are registered in `NoerdServiceProvider::boot()`; any module can add (or override) a view in its
own service provider:

```php
use Noerd\Services\DetailViewRegistry;
use Noerd\Support\DetailViewDefinition;

app(DetailViewRegistry::class)->register(new DetailViewDefinition(
    name: 'table',
    gridClasses: 'py-2 gap-0',       // spacing classes on the grid wrapper
    fullWidthRows: true,             // ignore per-field colspan
    numbersRows: false,              // no auto row numbering
    spacerClass: 'h-9',              // height of the `spacer` field type
));
```

Field elements resolve per convention — no block changes needed:

- `include` field types: `forms/{view}/<name>.blade.php` next to the original
  (e.g. `forms/table/input-select.blade.php`); the generic input fallback is `forms/{view}/input.blade.php`
- `livewire` field types: a `<name>-{view}` view alongside the component, namespace-aware
  (`mod::name` resolves `mod::components.name-{view}`)
- If no variant exists for a field type, the original element renders unchanged (graceful fallback)

Unknown view names silently fall back to `default` — a YAML typo never breaks a detail page. The
grid wrapper emits `data-view="{view}"` for non-default views (plus the legacy
`data-compact="true"` for compact).

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
| `action` | Livewire method called via `wire:click` (required) |
| `heroicon` | Optional heroicon rendered before the label |
| `confirm` | Optional confirmation prompt shown via `wire:confirm` (translation key) |
| `requiresId` | Defaults to `true` — the button is hidden until the record is saved (`modelId` is set). Set to `false` to always show it |

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
| `component` | List component opened as a modal on click (e.g. `contacts-list`, without the module prefix) |
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

A detail component declares its model as `public $detailModel` — everything else (mounting,
`store()`, `delete()`) comes from the `NoerdDetail` trait.

Example: `supplier-detail.blade.php`

```php
<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;
use Noerd\Accounting\Models\Supplier;

new class extends Component {
    use NoerdDetail;

    public $detailModel = Supplier::class;

    #[Url(as: 'supplierId', keep: false, except: '')]
    public $modelId = null;
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
