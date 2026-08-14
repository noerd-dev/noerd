# Create a List View

Lists display data in a table format with search, pagination, and actions.

![Noerd Example App](/assets/list.png "List View")

## File Locations

YAML Configuration:
```bash
app-configs/{app}/lists/{name}-list.yml
```

Livewire Component:
```bash
app-modules/{module}/resources/views/components/{name}-list.blade.php
```

List YAML files always live DIRECTLY in `lists/` — never in subfolders. A nested Livewire
component name (dots from a blade subfolder, e.g. `booking::bookings.types-list`) still
resolves its config by the flat file name (`lists/types-list.yml`); the subfolder segments
are ignored for lists. Layout overrides key off the same flat name. (Detail YAMLs keep
their dot-to-subfolder mapping.)

The config is resolved against the app folders the tenant may use — current app first, then the
other granted apps, then their module sources. A **namespaced** component (`communication::
communications-list`) additionally falls back to its OWN module's app folder
(`app-configs/communication/`), so a module reached from another app — linked from its navigation,
embedded in its detail, or opened as a modal from one of its tabs — still finds its title, columns
and fields even when the tenant was never granted that module as an app. The fallback is searched
last, so a granted app's own copy always wins. The same applies to detail and page YAMLs.

## Example YAML Configuration

Example: `app-configs/accounting/lists/customers-list.yml`

```yaml
title: Customers
actions:
  - label: New Customer
    action: listAction
disableSearch: false
columns:
  - field: name
    label: Name
    width: 12
    type: text
  - field: company_name
    label: Company Name
    width: 10
  - field: email
    label: Email
    width: 12
  - field: address
    label: Address
    width: 12
  - field: zipcode
    label: Zip Code
    width: 10
  - field: city
    label: City
    width: 10
```

## List Properties

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `description` | Optional description text under the title |
| `actions` | Array of action buttons (see Actions below) |
| `disableSearch` | Disable the search functionality |
| `showSummary` | Show or hide the summary row in the table footer (default: `true`) |
| `showLineNumbers` | Prepend an Excel-style row-number column (restarts on every page) |
| `multiSelect` | Enable the checkbox column and bulk-action bar on this list page (see Multi-Select & Bulk Actions below) |
| `bulkActions` | Array of bulk-action buttons shown when one or more rows are selected (see Multi-Select & Bulk Actions below) |
| `searchableColumns` | Restrict the search to specific fields (see [List Search](list-search.md)) |
| `notSortableColumns` | Fields whose header must not be sortable (see [List Search](list-search.md)) |
| `displayMode` | `table` (default) or `grid` — render the rows as a card grid instead of a table (see Grid Mode below) |
| `gridColumns` | Cards per row in grid mode at the largest breakpoint, `1`–`6` (default: `4`) |
| `columns` | Array of column definitions |

## Column Properties

| Property | Description | Default |
|----------|-------------|---------|
| `field` | Model attribute name | |
| `label` | Column header (translation key) | |
| `width` | Column width as CSS percentage | `10` |
| `minWidth` | Minimum width in pixels (`min-width`) | none |
| `align` | Text alignment (`left`, `right`; `number`/`currency` auto-align right) | `left` |
| `type` | Display type (see Column Types below) | `text` |
| `options` | `value`/`label` pairs for the `badge` type (see below) | |
| `readOnly` | For editable cell types (`bool`, inline inputs): render read-only | `true` |
| `action` | Livewire method called on cell click (receives the row id) | `openListRow` |
| `actions` | Array of row actions rendered as a dropdown in this column — entries support `label`, `route`, `modalComponent`, `action`, `confirm`, `heroicon` | |
| `wireClick` / `wireClickField` | For `colored_text`: custom `wire:click` method plus the row field passed as its argument | / `id` |

**Width behavior:** The `width` value is applied as `style="width: 15%;"` on the `<th>` element. If the sum of all column widths exceeds 100, the table becomes wider than its container and horizontal scrolling is enabled.

## Column Types

| Type | Description |
|------|-------------|
| `text` | Default. Standard text display |
| `date` | Formats value as date (YYYY-MM-DD) |
| `datetime` | Formats value as date + time (`d.m.Y H:i` in German locale, `Y-m-d H:i` otherwise) |
| `number` | Right-aligned number, rounded to 2 decimals |
| `currency` | Right-aligned number formatted as currency with `€` |
| `id` | Clickable ID link |
| `bool` | Toggleable boolean: green checkmark (true), red circle (false). Clickable to toggle value |
| `inversebool` | Green checkmark when true, nothing when false. Clickable to toggle value |
| `badge` | Neutral badge; the raw value is translated to a label via the column's `options` (`value`/`label` pairs). Columns mirroring a paired detail `type: select` field get this automatically |
| `badge_with_text` | Badge with optional text (value must be array with `badge` and `text` keys) |
| `relationBadge` | Badge showing the display title of a foreign-key value (resolved via the registered relation types) |
| `customAttribute` | Value from the `custom_attributes` JSON column, normalized for display (translatable arrays are resolved to the active language) |
| `colored_text` | Text with optional color classes; the row value may be an array with `text`, `class`, `prefix`, `prefixClass`, `icon` keys |
| `relation_link` | Clickable link that opens a modal (requires `idField` plus either `route` or `modalComponent` in the column config; in route mode `idParam` defaults to `modelId`) |

**Automatic typing:** Columns without an explicit `type` are typed from the database schema
(`boolean` → `bool`, numeric → `number`, `date`/`datetime` → matching type), and columns whose
field mirrors a `type: select` field in the paired detail YAML (`{x}-list` → `{x}-detail`) render
as translated badges automatically. An explicit `type` or own `options` always wins.

**Example:**

```yaml
columns:
  - field: name
    label: Name
    width: 30
    type: text
  - field: start_date
    label: Start Date
    width: 15
    type: date
  - field: is_active
    label: Active
    width: 10
    type: bool
  - field: is_emergency
    label: Emergency
    width: 10
    type: inversebool
```

## Livewire Component

A list component declares its model as `public $listModel` and its detail target as
`public ?string $detailRoute` (preferred) and/or `public $detailComponent` — everything
else (query, modal opening, mounting, request handling) comes from the `NoerdList` trait.

Example: `customers-list.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Customer\Models\Customer;

new class extends Component {
    use NoerdList;

    public $listModel = Customer::class;
    public ?string $detailRoute = 'customer.detail';
    public $detailComponent = 'customer::customer-detail';
};
?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
```

The trait defaults build the query via `listQuery($this->listModel)` (which applies search,
sort and the Excel-style column filters from the YAML config) and open the detail as a modal
on row click.

`$detailRoute` wins when the named route is registered: the record opens as a modal AND the
browser URL is rewritten to `/{app}/{entity}/{id}?modal=true`, so the link is shareable and a
reload reopens the record over the previously visited page. `$detailComponent` stays as the
fallback — keep both, so a list may reference a detail route owned by an optional module.
See [Modal System](modal.md#route-modals).

### Custom Query Logic

When the list needs its own query (eager loads, extra wheres, row transformations),
override `listData()` — keep `$listModel` declared and still build the query via
`listQuery($this->listModel)`. Example: `products-list.blade.php`

```php
new class extends Component {
    use NoerdList;

    public $listModel = Product::class;
    public $detailComponent = 'product::product-page';

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)
            ->whereDoesntHave('products', fn($query) => $query->withoutGlobalScopes())
            ->with('groups')
            ->paginate($this->perPage);

        foreach ($rows as $row) {
            $row->price_preview = $row->getPriceWording() . number_format($row->getGrossPrice(), 2, ',', '.');
        }

        return $this->buildList($rows);
    }
}; ?>
```

Never leave a custom query in `with()` once `$listModel` is declared: the generic trait
features (row click, select-all, bulk delete) resolve the list via `listData()` and would
operate on the wrong rows. Extra view data (e.g. a footer summary) stays in a slim `with()`
that returns only the extra keys — share the query between `listData()` and `with()` via a
private helper method:

```php
private function filteredQuery(): Builder
{
    return $this->listQuery($this->listModel)
        ->when($this->customerId, fn ($query) => $query->where('customer_id', $this->customerId));
}

public function listData(): array
{
    return $this->buildList($this->filteredQuery()->paginate($this->perPage));
}

public function with(): array
{
    return [
        'summary' => [
            'name' => __('Total'),
            'total_gross' => $this->filteredQuery()->toBase()->sum('total_gross'),
        ],
    ];
}
```

## Key Concepts

- **Trait:** `NoerdList` provides all necessary properties and methods
- **$listModel:** The Eloquent model backing the list — required for the trait defaults and the header actions (layout/object manager)
- **$detailRoute:** Named detail route opened by `listAction()` — rewrites the browser URL to the record (preferred)
- **$detailComponent:** The detail component opened by `listAction()` when no `$detailRoute` is registered
- **listData():** Builds the list config; override it for custom queries, always ending in `return $this->buildList($rows);`
- **listAction():** Trait default opens `$detailRoute` (else `$detailComponent`) as a modal with `['modelId' => $modelId]`; only override it for custom behavior (extra modal arguments, no modal, …)
- **buildList():** Generates the list configuration from the YAML
- **request()->customerId / request()->create:** Handled by the trait's `rendering()`; override `rendering()` when the list uses its own URL parameter (e.g. `invoiceId`)
- **`<x-noerd::list />`:** Renders the table
- **Object permissions:** Read/write/delete denial via the optional `noerd.object-*` gates (see
  `AccessHelper` in extension-registries.md) hides rows, header actions and the delete bulk action. The permission target is the model resolved
  by `listQuery()` / the declared `$listModel`. A repository-backed list without `$listModel`
  declares it explicitly — `public ?string $objectPermissionModel = Order::class;` — otherwise it
  stays unrestricted (reference: the liefertool orders list)

## Default Sorting

To set a custom default sort order, use `setDefaultSort()` in your `mount()` method:

```php
public function mount(): void
{
    $this->mountList();
    $this->setDefaultSort('created_at', false);  // Sort by created_at descending
}
```

**Parameters:**
- `$field`: Column name to sort by
- `$ascending`: `true` for ascending (A-Z), `false` for descending (Z-A)

Without `setDefaultSort()`, lists default to `id` descending.

See [List Search](list-search.md) for more details on search and sorting.

## Actions

List components support multiple action buttons via the `actions` array in the YAML configuration.

**YAML Configuration:**

```yaml
actions:
  - label: accounting_label_import
    action: openImportModal
    heroicon: arrow-up-tray
  - label: accounting_label_new_transaction
    action: listAction
```

| Property | Description |
|----------|-------------|
| `label` | Translation key for the button text |
| `route` | (optional) Named route opened as a modal — use this instead of `action: listAction` for the "New …" button |
| `arguments` | (optional, with `route`) Arguments passed to the modal |
| `action` | Livewire method name to call (used when no `route` is given) |
| `heroicon` | (optional) Heroicon name for the button icon |
| `style` | (optional) Set to `secondary` for secondary button style. Default is primary |

**Button layout:**
- All buttons are primary style by default
- Set `style: secondary` on individual actions for secondary style
- Keyboard shortcut (N) applies only to the first button
- Buttons are displayed side by side
- No `actions` key means no button is rendered

**Where the controls render (generic injection):** the header controls — search, CSV export,
registry list actions and the YAML action buttons — are injected by `x-noerd::modal-title`
(`noerd::components.table.list-controls`) for EVERY component using the `NoerdList` trait. This
covers the standard list header (`list-header` contributes only title, count, view switcher and
filter chips) AND components with their own custom `<x-slot:header>` (e.g. a list nested in tab
panels like the object manager): wrap the custom title in `<x-noerd::modal-title>` and the
controls appear top right automatically — never hand-roll a search field or action buttons in a
list header. Two props on `x-noerd::modal-title` tune the injection:

| Prop | Description |
|------|-------------|
| `:listControls="false"` | Suppresses the injection (for headers without a real list behind them) |
| `listControlsShow` | Alpine expression gating the controls' visibility, e.g. `currentTab === 2` |

**Standard single action (most common):**

```yaml
title: Customers
actions:
  - label: New Customer
    route: customer.detail
```

`route:` opens the detail route as a modal and writes `/customer/new?modal=true` into the
address bar. `action: listAction` is the component-based equivalent and stays valid for
lists whose detail has no route.

**Multiple actions with icon:**

```yaml
title: accounting_label_bank_transactions
actions:
  - label: accounting_label_import
    action: openImportModal
    heroicon: arrow-up-tray
  - label: accounting_label_new_transaction
    action: listAction
```

**PHP method for custom actions:**

```php
public function openImportModal(mixed $modelId = null, array $relations = []): void
{
    Noerd::modal('bank-transaction-import');
}
```

Requires the facade import: `use Noerd\Facades\Noerd;`

Custom methods must accept `(mixed $modelId = null, array $relations = [])` parameters to match the expected signature.

## Multi-Select & Bulk Actions

Lists support a generic **multi-select** mode: a leading checkbox column plus a footer bar that acts
on the ticked rows. The selected ids are tracked in the generic `public array $selectedRecordIds`
property on the `NoerdList` trait — never re-implement this per list. There are two flavours.

### 1. Bulk-action page

Set `multiSelect: true` in the list YAML to show checkboxes on the list page. Row clicks still open
the detail (only the checkbox ticks a row). When one or more rows are selected, a footer bar renders
the buttons from the YAML `bulkActions` array.

```yaml
title: Tasks
multiSelect: true
bulkActions:
  - label: Assign to
    action: assignSelected            # list-specific method on the component
    heroicon: user-plus
    style: secondary
  - label: Delete
    action: deleteSelected            # generic NoerdList method — works for any list
    heroicon: trash
    style: danger
    confirm: Delete the selected entries?   # optional: shown via wire:confirm
columns:
  - field: title
    label: Title
```

**`bulkActions` properties:**

| Property | Description |
|----------|-------------|
| `label` | Button text (translation key) |
| `action` | Livewire method called on the list component (required) |
| `heroicon` | (optional) Heroicon name for the button icon |
| `style` | (optional) `secondary` or `danger`. Default is `primary` |
| `confirm` | (optional) Confirmation prompt (translation key) shown via `wire:confirm` |

**Generic vs. list-specific actions:**

- **`deleteSelected()` lives in `NoerdList`** — it deletes every selected id through the
  tenant-scoped query (firing model events, so observers/auditing still run). Wire it up purely from
  YAML; no per-list method is needed.
- **List-specific actions** are public methods you add to the list component. They read the ticked ids
  from `$this->selectedRecordIds`. Example — open the task-create modal for the selected records:

  ```php
  use Noerd\Facades\Noerd;

  public function createTaskForSelected(): void
  {
      Noerd::modal('crm::task-create-modal', [
          'targetType' => 'Account',
          'selectedIds' => $this->selectedRecordIds,
      ]);
  }
  ```

After a bulk action that should clear the selection (e.g. opening a follow-up modal that finishes the
job), reset it in a listener so the checkboxes clear:

```php
use Livewire\Attributes\On;

#[On('tasksAssigned')]
public function onTasksAssigned(): void
{
    $this->selectedRecordIds = [];
}
```

### 2. Picker (return a selection to an opener)

Open any list as a modal with `multiSelect` **and** `returnsSelection` to use it as a record picker.
In picker mode a row click ticks the row, the top "New …" action is hidden, and the footer shows
**Cancel / Apply selection** instead of the bulk actions.

```php
Noerd::modal('crm::accounts-list', [
    'multiSelect' => true,
    'returnsSelection' => true,
    'selectedRecordIds' => $this->selectedIds,   // pre-tick the current selection
    'context' => 'taskRecords',                  // disambiguates the result event
]);
```

On confirm the list dispatches `recordsSelected` with `ids` and `context`; the opener listens and
filters by its `context`:

```php
#[On('recordsSelected')]
public function recordsSelected(array $ids, mixed $context = null): void
{
    if ($context !== 'taskRecords') {
        return;
    }

    $this->selectedIds = array_values(array_map('intval', $ids));
}
```

### Generic API on `NoerdList`

| Member | Purpose |
|--------|---------|
| `bool $multiSelect` | Enable the checkbox column (prop, or `multiSelect: true` in YAML) |
| `bool $returnsSelection` | Picker mode — row click ticks, footer is Cancel / Apply selection |
| `array $selectedRecordIds` | The ticked ids |
| `toggleRecordSelection($id)` | Toggle one row (wired to the row checkbox and, in picker mode, the row click) |
| `toggleSelectAllVisible()` | Toggle every row on the current page (the header checkbox) |
| `confirmRecordSelection()` | Dispatch `recordsSelected` + `closeTopModal` (picker footer) |
| `deleteSelected()` | Generic bulk delete of the selected records |

**Notes:**

- Multi-select is always **off in compact/embedded lists** — checkboxes only appear on full pages and
  in pickers, never in a list embedded inside a detail view.
- The checkbox's checked state is part of its `wire:key`, so the DOM is recreated when the selection
  changes — this guarantees the checkboxes clear after a bulk action (a plain morph can leave a
  user-toggled checkbox visually checked).
- The footer (picker confirm bar vs. bulk-action bar) is decided by `returnsSelection`: when set, the
  confirm bar wins; otherwise the YAML `bulkActions` render once at least one row is selected.
- Reference: `app-configs/crm/lists/tasks-list.yml` (bulk `Assign to` + `Delete`),
  `leads-list`/`accounts-list` (`createTaskForSelected`) for the page flavour;
  `task-create-modal.blade.php` `openRecordPicker()` for the picker.

## Compact Mode (Embedded Lists)

Use **compact mode** when embedding a list inside another component — for example a related list
rendered below the form of a detail view. In compact mode the list renders only the table and hides:

- the list header (title, search field and action buttons such as "New …")
- the inline title-search and description
- the pagination footer (the "Showing 1 to N of N results" row and the per-page select)

`compact` is a public property on the `NoerdList` trait, so it works exactly like `disableModal` —
just add it as an attribute on the embedded Livewire component.

> **For detail views, don't wire this up by hand.** Use the generic `<x-noerd::detail-lists>`
> component instead — it renders the heading, the breakout wrappers and the compact list from a
> `lists` array in the detail YAML. See [Embedded Lists in Detail Views](detail-view.md#embedded-lists).

The low-level flag (used internally by `<x-noerd::detail-lists>`):

```blade
{{-- mx-8 cancels the disableModal -2rem breakout so the list aligns with the surrounding form --}}
<div class="mx-8">
    <livewire:crm::opportunities-list
        wire:key="account-opportunities-{{ $modelId }}"
        disableModal
        compact
        :accountId="$modelId" />
</div>
```

**Notes:**

- `noerd::components.list` reads the flag via `$compact = $compact ?? ($this->compact ?? false);`,
  so the behaviour is generic — never duplicate it per module.
- Compact mode also removes pagination, so only the first `perPage` rows are shown. Use it for
  narrowly-scoped lists (e.g. records that belong to the current detail record).
- A list embedded with `disableModal` breaks out by `-2rem` (intended for full-page routes); the
  wrappers re-pad it so it aligns cleanly inside a modal or detail view.
- `disableModal` never needs to be passed to `<x-noerd::page>` in the component's own view —
  the page component reads the flag from the Livewire component automatically
  (`$disableModal = $disableModal ?? (($__livewire ?? null)?->disableModal ?? false);`). An explicit
  `:disableModal="true"` attribute still overrides the property.

## Multiple List Views (View Switcher)

A list can ship **multiple YAML views** — alternate configurations of the same list (different
columns, title, actions). When at least two views exist, the list title turns into a dropdown
button (title + record count + chevron) that lets the user switch the active view, similar to
Salesforce list views.

**Naming convention** — sibling files in the same `lists/` folder, suffixed with `--{key}`:

```bash
app-configs/customer/lists/
├── customers-list.yml          # the default view
├── customers-list--vip.yml     # view "VIP Customers"
└── customers-list--inactive.yml
```

- The view key is the suffix after `--` (e.g. `vip`); `--` is therefore reserved as the view
  separator and must not appear in list names themselves.
- Each view file is a **complete standalone list config** (title, columns, actions, …) — nothing is
  merged from the base file.
- Views may be **project-only**: a `customers-list--vip.yml` in the project's `app-configs/` without
  a module copy is fine. Within one app a project file shadows a module-source file with the same
  view key.

**Cross-app enumeration** — the dropdown lists the views of EVERY app allowed for the tenant, not
just the session's current app. A list name that exists in several apps (e.g. `customers-list` in
`delivery` and `customer`) yields one entry per app, each labelled with its source app rendered
with reduced opacity — e.g. "Kunden (Delivery)":

- Every entry shows its source app label (the `TenantApp` title; `Setup` for the setup folder).
- Current-app entries use plain view keys (`default`, `vip`); other apps' entries use composite
  `{app}::{key}` keys (`delivery::default`, `delivery::vip`). `::` is therefore reserved and cannot
  appear in view keys.
- Selecting another app's view renders that app's YAML via explicit-app resolution
  (`StaticConfigHelper::getListConfigForApp()`); the session's selected app is NOT changed.
- Ordering: current app first, then the other allowed apps; `default` leads each app group,
  remaining variants alphabetical.
- The dropdown label is the view file's `title` (translated via `__()`).

**Behaviour:**

- The switcher only renders when ≥2 entries exist (across all apps), and never in compact/embedded
  lists or pickers.
- The selected view is remembered per list in the session (`listView.{component}`) — as the
  composite `{app}::{key}` when it belongs to another app. If the view's YAML is removed, the list
  silently falls back to the default view.
- The active view is also reflected in the URL as `?view={key}` (plain `vip`, composite
  `{app}--vip` — `--` instead of `::` keeps `%3A%3A` encoding out of the URL — or `default` for
  the standard view), so a shared link opens the same view — the default view included. On page
  load the URL param takes precedence over the session-saved view (and is persisted to the session,
  in `::` form); an unknown key falls back to the session/default. Single-view lists never carry
  the param; embedded compact lists and pickers never read or write it.
- Because the whole config is swapped, the view's own `searchableColumns`, `actions`,
  `notSortableColumns` and column types all apply automatically. Layout overrides (noerd-plus) key
  per view file (e.g. `customers-list--vip`), app-agnostic — a restriction on `vip` also hides
  every other app's `{app}::vip` entry.

**Generic API:**

| Member | Purpose |
|--------|---------|
| `?string $listView` | Active plain view key (`null` = base YAML) on the `NoerdList` trait |
| `?string $listViewApp` | Source-app folder of the active view (`null` = current app) |
| `?string $listViewParam` | URL-bound (`#[Url(as: 'view')]`) key of the active view incl. `'default'`; composite keys use `--` (`gastro--vip`); `null` = single-view/embedded list |
| `switchListView(string $key)` | Switch and persist the active view (`'default'` = base YAML; accepts composite keys) |
| `availableListViews` (computed) | `['{viewKey}' => ['key' => …, 'app' => …, 'appLabel' => …, 'title' => …], …]` |
| `StaticConfigHelper::getListViews($component)` | The underlying cross-app discovery helper |
| `StaticConfigHelper::getListConfigForApp($app, $name)` | Load a list config for an explicit app |
| `StaticConfigHelper::parseListViewKey($key)` | `[appFolder\|null, plainKey]` from a dropdown key |
| `StaticConfigHelper::composeListViewKey($app, $key)` | Inverse of `parseListViewKey()` |

## CSV Export

A list can offer a CSV download of its (filtered) query. Enable it with the component property
`public bool $enableCsvExport = true;` and override `prepareCsvExport()`:

```php
protected function prepareCsvExport(): array
{
    $config = $this->getListConfig();

    return [
        $this->listQuery($this->listModel),   // Builder — search/sort/filters already applied
        $config['columns'] ?? [],             // columns to export (list YAML shape)
        'customers.csv',                      // download filename
    ];
}
```

- The export button appears in the list header next to the search field
- The file streams with a UTF-8 BOM and `;` as delimiter (Excel-friendly); headers are the
  translated column labels; rows are read lazily in chunks
- `formatCsvValue($value, $column)` formats each cell by column type (`bool` → Yes/No, `badge` →
  the translated option label, dates/currency accordingly) — override it for custom formats
- `prepareExportRow($row)` is an optional per-row hook (e.g. to eager-compute accessors)

## Grid Mode (Card Layout)

Any list can render its rows as a **card grid** instead of the table — purely through the list
YAML, with zero changes to the component's Blade file:

```yaml
title: POS
description: Select a customer to start a new order.
displayMode: grid
gridColumns: 4
columns:
  - field: name
    label: Name
  - field: company_name
    label: Company
  - field: city
    label: City
searchableColumns:
  - name
  - company_name
  - city
```

Grid mode swaps only the rows block (`noerd::components.list.grid`) — the list header (title,
search, view switcher, actions, filter chips), the pagination footer, the picker/bulk footers and
the object-permission handling stay exactly as in table mode.

**Card content** is derived from the `columns` array: the first column with a non-empty value
renders as the bold card title, every remaining column as a secondary line; empty values are
skipped entirely (so a missing `name` falls through to the next column as the title). Column types
are honored like in minimal mode: `currency`, `date`, `datetime`, `bool` are formatted, `badge`
renders as a translated pill; everything else renders as text (`data_get`, so dotted fields work).

**Cards per row**: `gridColumns` (`1`–`6`, default `4`) sets the count at the largest breakpoint;
smaller viewports collapse responsively (1 column on mobile, 2 from `sm`, 3 from `lg`). The classes
come from a static map — Tailwind cannot generate class names at runtime, so only these values are
supported; an unknown value falls back to `4`.

**Row click** behaves exactly like a table row click (`openListRow` → `$detailRoute` /
`$detailComponent`, or a custom `listAction()` override), including the keyboard navigation
(arrow keys + Enter) and picker mode. In `multiSelect` mode each card gets a checkbox in its
top-right corner wired to `toggleRecordSelection`.

**Not rendered in grid mode** (thead-only features): column sort headers (the default sort from
`setDefaultSort()` still applies), the Excel-style column-filter funnels (active filters still
apply to the query and stay visible/clearable via the header filter chips), the select-all
checkbox, `showLineNumbers` and the summary footer.

A list mounted as a **minimal widget** ignores `displayMode` — minimal mode takes precedence.

## Minimal Mode (List Widgets)

Besides [compact mode](#compact-mode-embedded-lists), a list can render as a **minimal widget**: a
slim, column-restricted variant used by the page widget sidebar (`<x-noerd::detail-widget>` mounts
the embedded list this way). Mount properties:

| Property | Description |
|----------|-------------|
| `minimal` | Enable the minimal variant |
| `minimalColumns` | Field names to render, in order (subset of the YAML columns) |
| `minimalLimit` | Row limit (default `5`; replaces pagination) |
| `showMoreRoute` | Named list route for the "Show more" link — opened as a modal WITHOUT rewriting the URL (the modal shows the list narrowed by `showMoreArguments`) |
| `showMoreComponent` | List component for "Show more" — fallback when the route is not registered |
| `showMoreArguments` | Arguments passed to the "Show more" list (usually the same narrowing filter) |

```blade
<livewire:crm::opportunities-list
    wire:key="widget-opportunities-{{ $modelId }}"
    :minimal="true"
    :minimalColumns="['name', 'amount']"
    :showMoreRoute="'crm.opportunities'"
    :showMoreArguments="['accountId' => $modelId]" />
```

## Next Steps

Continue with [Create a Detail View](detail-view.md) to build forms for editing records.
