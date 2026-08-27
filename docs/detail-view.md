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
app-modules/{module}/resources/views/components/{name}-detail.blade.php
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
| `quickCreate` | Open the "new record" dialog as a narrow quick-create modal |
| `tabs` | Array of tab definitions |
| `fields` | Array of form field definitions |
| `actions` | Array of action button definitions rendered above the form (see [Detail Actions](#detail-actions)) |

> **Note:** `relations:` (Relation Box) and `widgets:` are PAGE concerns — they live in the
> optional page YAML (`pages/{entity}-page.yml`), not in a detail YAML. See [Page View](page-view.md).

## Tab Properties

| Property | Description |
|----------|-------------|
| `number` | Tab index (1-based) |
| `label` | Tab label (translation key) |
| `component` | Embedded Livewire component |
| `arguments` | Arguments passed to embedded component; the `$modelId` token resolves to the current record id |
| `requiresId` | Only show tab when editing existing record |
| `permission` | Gate ability required to see the tab; `permissionModel` (optional) is passed as the ability's model argument |
| `viewExists` | View name — the tab is hidden when that view is not registered (safe reference to an optional module) |
| `showIf` | Reactive client-side visibility: a `$wire` property name (string) or `{field: ..., value: ...}` |
| `modalRoute` | Named route opened as a modal instead of switching panels (with optional `routeParameters`) |
| `route` | Named route the tab navigates to (full page load) |
| `routable` | With `component`: makes the tab addressable via the generic `/noerd/component-page/{componentName}` route |

### Hand-Rolled Tab Panels

When a component builds its tab panels manually (instead of via `<x-noerd::tab-content>`), always
use the generic `<x-noerd::tab-panels>` / `<x-noerd::tab-panel>` components — never a bare
`x-show` div. They keep the modal height constant across tabs and give every panel its own scroll
container. `<x-noerd::tab-panel>` accepts `number` and an optional `show` prop with an Alpine
expression for reactive visibility on top of the tab switch:

```blade
<x-noerd::tab-panels>
    <x-noerd::tab-panel :number="1">…</x-noerd::tab-panel>
    <x-noerd::tab-panel :number="2" :show="'$wire.someFlag'">…</x-noerd::tab-panel>
</x-noerd::tab-panels>
```

## Field Properties

| Property | Description |
|----------|-------------|
| `name` | Property path (e.g., `detailData.name`) |
| `label` | Field label (translation key) |
| `helpText` | Explanation shown as a tooltip behind a question-mark icon next to the label (translation key); works in every theme |
| `type` | Field type (text, textarea, checkbox, relation, etc.) |
| `required` | Mark field as required |
| `readonly` | Render the field read-only/disabled (also forced on every field when the user's object permission denies writing, see below) |
| `colspan` | Grid column span (1-12) |
| `tab` | Tab number (defaults to 1) |
| `theme` | Per-field theme override (see [Themes](themes.md)) |
| `number` | Explicit row number in the `numbered` theme (defaults to auto-increment) |

## Relation Forms

A field name may point into a RELATED model (e.g. `detailData.invoiceAddress.address_line_1`):
the framework hydrates the related record's values on load and persists them after every save,
with zero component code. Relation forms are declared on the model via the
`DeclaresRelationForms` contract — see [Relation Forms](relation-forms.md).

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

## Read-Only Rendering on Write-Denied Objects

When the `noerd.object-write` gate (see `AccessHelper`) denies **write** for the detail's
`$detailModel`, the whole YAML form renders read-only —
in every theme. The mechanism is a single seam in the detail block: it consults the hosting
component's `canWriteObject()` and forces `readonly: true` onto every field before the element
templates and relation-field props are resolved. Text inputs/textareas get the `readonly`
attribute, selects/picklists/checkboxes are `disabled`, upload and picker affordances are hidden,
the rich-text editor becomes non-editable, and `type: button` fields render disabled. Relation
field components additionally guard their wire-reachable mutators (`clear()`, selection) on the
server.

Notes:

- The client-side readonly state is a UX affordance — the security boundary stays the
  `store()`/`delete()` guards in `NoerdDetail`/`NoerdPage`.
- Hand-written markup in tab slots (custom `tab1` content, embedded components) is NOT covered by
  the generic mechanism. Hosts with such markup consult `$this->canWriteObject()` themselves and
  disable their controls accordingly.
- Components without `canWriteObject()` (no `NoerdDetail`/`NoerdPage`) are never restricted.

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

Action buttons render a row above the form. Each button calls a Livewire method on the detail component itself. Use this for record-level operations such as "Transfer to Account" or "Generate PDF".

### Automatic Rendering

`<x-noerd::page>` renders the actions row automatically as the first element of the page body
whenever the component's `$pageLayout` carries an `actions:` array — a detail blade needs NO
`<x-noerd::detail-actions>` include. Adding an action is purely a YAML change.

The auto-render is skipped for embedded details, quick-create dialogs, and components without a
`$pageLayout` property (e.g. lists — the list-level `actions:` key is a different concept).

A blade opts out via the `detailActions` attribute — do this when the layout needs custom logic
(e.g. conditionally suppressing the actions) and render `<x-noerd::detail-actions>` explicitly
instead (otherwise the row would render twice):

```blade
<x-noerd::page :detailActions="false">
    ...
    <x-noerd::detail-actions :layout="$condition ? $pageLayout : []" :modelId="$modelId" />
```

The explicit component also remains the right tool for hand-built (non-YAML) action layouts, and
for a detail that must show its actions when rendered **embedded** in a hosting page (the embedded
chrome renders only the slot, so the auto-render never runs there — see `pdm::assembly-detail`).

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
| `url` | Renders the action as a plain link (`<a href>`) instead of a button — either a literal URL (`http…` / `/…`) or a key in the `urls` map (see [Link Actions](#link-actions)) |
| `newTab` | Only with `url:` — defaults to `true` (`target="_blank"`). Set to `false` to open in the same tab |
| `action` | Livewire method called via `wire:click` (used when neither `route`, `modalComponent` nor `url` is set) |
| `heroicon` | Optional heroicon rendered before the label |
| `confirm` | Optional confirmation prompt shown via `wire:confirm` (translation key) |
| `loading` | Only with `action:` — alternate label shown while the method runs (`wire:loading`, translation key); the button is disabled meanwhile |
| `requiresId` | Defaults to `true` — the button is hidden until the record is saved (`modelId` is set). Set to `false` to always show it |
| `showIf` | Show the button only while a component property is truthy — or, in the object form (`field:` / `value:`), equals a value (see [Conditional Actions](#conditional-actions)) |
| `showIfNot` | The negated form of `showIf`. Both may sit on the same action and are combined with AND |
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

### Conditional Actions

`showIf` / `showIfNot` mirror the field- and tab-level conditions: the button carries an Alpine
`x-show` bound to the detail component's state, so it follows a status property without a page
reload. Both keys may sit on the same action (combined with AND):

```yaml
actions:
  - label: Send account invitation
    action: sendAccountInvite
    heroicon: envelope
    showIf: hasEmail
    showIfNot: hasAccount
  - label: Login as customer
    action: loginAsCustomer
    heroicon: arrow-right-on-rectangle
    showIf: hasAccount
```

The string form checks a public property for truthiness (`hasAccount`, or a dotted path into an
array property such as `detailData.is_business`). The object form compares against a value:

```yaml
    showIf:
      field: detailData.status
      value: open
```

Use it for record STATE that changes while the modal is open. Structural conditions keep their own
keys: `requiresId` for "record not saved yet" and `viewExists` for "module not installed". When
EVERY action is conditional, the action bar itself is hidden along with its buttons, so a fully
suppressed row leaves no empty box behind.

### Link Actions

A `url:` action renders as a link that opens in a new tab — use it for targets outside the backend
(a public guest page, an external system). A record-dependent URL is computed by the detail component
and exposed through a public `detailActionUrls()` method — the auto-rendered actions row picks it up
by convention; the YAML only names the key:

```php
public function detailActionUrls(): array
{
    return ['tableUrl' => $this->tableUrl];
}
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

The Relation Box (a grid of clickable tiles showing related record counts) is a PAGE feature:
the `relations:` array lives in the page YAML (`pages/{entity}-page.yml`) and the
`<x-noerd::detail-relations>` component is placed in the `*-page` blade. See
[Page View → Relation Box](page-view.md#relation-box).

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

## Further UI Components

- **`<x-noerd::toolbar :buttons="[...]">`** — a horizontal action/status bar. Each entry is an
  array with `label`, `action`, optional `heroicon`, `confirm`, `disabled`; `type: separator`
  renders a divider, `type: status` a colored status chip (`variant: success|warning|neutral`).
- **`<x-noerd::code-snippet label="..." language="blade">`** — renders the slot content as a dark
  code panel with a copy button; useful on settings pages that show embed codes.
- **`<x-noerd::help-tooltip text="...">`** — the question-mark tooltip used by `helpText`; can be
  placed manually next to custom labels.

## Naming Conventions

- Lists: `{plural}-list.blade.php` (e.g., `customers-list.blade.php`)
- Details: `{singular}-detail.blade.php` (e.g., `customer-detail.blade.php`)
- Components live directly in the `components/` folder by default. Nested component names are
  supported (e.g. `booking::bookings.types-list`): DETAIL YAMLs map the dots to subfolders
  (`details/bookings/booking-detail.yml`), LIST YAMLs always stay flat in `lists/` — the dot
  segments are ignored for lists (see [List View](list-view.md))

## Next Steps

- [Field Types](field-types.md) - All available field types and their options
- [Page View](page-view.md) - Page chrome, relations, widgets around a detail form
- [Creating Modules](creating-modules.md) - Build independent modules
