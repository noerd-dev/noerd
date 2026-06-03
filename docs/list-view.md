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
| `actions` | Array of action buttons (see Actions below) |
| `disableSearch` | Disable the search functionality |
| `showSummary` | Show or hide the summary row in the table footer (default: `true`) |
| `columns` | Array of column definitions |

## Column Properties

| Property | Description | Default |
|----------|-------------|---------|
| `field` | Model attribute name | |
| `label` | Column header (translation key) | |
| `width` | Column width as CSS percentage | `10` |
| `minWidth` | Minimum width in pixels (`min-width`) | none |
| `align` | Text alignment (`left`, `right`) | `left` |
| `type` | Display type (see Column Types below) | `text` |

**Width behavior:** The `width` value is applied as `style="width: 15%;"` on the `<th>` element. If the sum of all column widths exceeds 100, the table becomes wider than its container and horizontal scrolling is enabled.

## Column Types

| Type | Description |
|------|-------------|
| `text` | Default. Standard text display |
| `date` | Formats value as date (YYYY-MM-DD) |
| `number` | Right-aligned number, rounded to 2 decimals |
| `currency` | Right-aligned number formatted as currency with `€` |
| `id` | Clickable ID link |
| `bool` | Toggleable boolean: green checkmark (true), red circle (false). Clickable to toggle value |
| `inversebool` | Green checkmark when true, nothing when false. Clickable to toggle value |
| `badge_with_text` | Badge with optional text (value must be array with `badge` and `text` keys) |
| `relation_link` | Clickable link that opens a modal (requires `modalComponent` and `idField` in column config) |

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

Example: `customers-list.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Customer\Models\Customer;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('customer-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = Customer::paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->id) {
            $this->listAction(request()->id);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">

    <x-noerd::list />

</x-noerd::page>
```

## Key Concepts

- **Trait:** `NoerdList` provides all necessary properties and methods
- **No constants needed:** The trait handles component identification
- **listAction():** Dispatches modal events to open detail views with `['modelId' => $modelId]`
- **$this->getComponentName():** Returns the current component name for the `source` parameter
- **buildList():** Generates the list configuration from the YAML
- **request()->id:** URL parameter for direct access to a specific record
- **`<x-noerd::list />`:** Renders the table

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
| `action` | Livewire method name to call (always explicit, no fallback) |
| `heroicon` | (optional) Heroicon name for the button icon |
| `style` | (optional) Set to `secondary` for secondary button style. Default is primary |

**Button layout:**
- All buttons are primary style by default
- Set `style: secondary` on individual actions for secondary style
- Keyboard shortcut (N) applies only to the first button
- Buttons are displayed side by side
- No `actions` key means no button is rendered

**Standard single action (most common):**

```yaml
title: accounting_label_customers
actions:
  - label: accounting_label_new_customer
    action: listAction
```

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

## Next Steps

Continue with [Create a Detail View](detail-view.md) to build forms for editing records.
