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
| `tabs` | Array of tab definitions |
| `fields` | Array of form field definitions |
| `actions` | Array of action button definitions rendered above the form (see [Detail Actions](#detail-actions)) |
| `relations` | Array of relation tile definitions rendered as a Relation Box above the form (see [Relation Box](#relation-box)) |
| `footerComponents` | Array of Livewire components rendered in the footer bar |

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

Example: `customer-detail.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdDetail;
use Noerd\Customer\Models\Customer;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = Customer::class;

    public function store(): void
    {
        $this->validateFromLayout();

        $customer = Customer::updateOrCreate(['id' => $this->modelId], $this->detailData);

        $this->showSuccessIndicator = true;

        if ($customer->wasRecentlyCreated) {
            $this->modelId = $customer->id;
        }
    }

    public function delete(): void
    {
        $customer = Customer::find($this->modelId);
        $customer->delete();
        $this->closeModalProcess($this->getListComponent());
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Kunde</x-noerd::modal-title>
    </x-slot:header>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"
            :footerComponents="$pageLayout['footerComponents'] ?? []"
            :modelId="$modelId ?? null"/>
    </x-slot:footer>
</x-noerd::page>
```

## Key Concepts

- **Trait:** `NoerdDetail` provides `$detailData`, `$modelId`, `$pageLayout`, and helper methods
- **Constant:** Only `DETAIL_CLASS = Model::class` is required
- **Properties:** `$detailData` (array) for form binding, `$modelId` (from trait) for the record ID
- **mount():** Handled by the trait automatically - no need to define it
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
