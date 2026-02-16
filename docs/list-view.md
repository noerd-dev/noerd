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
app-modules/{module}/resources/views/components/⚡{name}-list.blade.php
```

## Example YAML Configuration

Example: `app-configs/accounting/lists/customers-list.yml`

```yaml
title: accounting_label_customers
newLabel: accounting_label_new_customer
component: customer-detail
disableSearch: false
redirectAction: ''
columns:
  - field: name
    label: accounting_label_name
    width: 12
    type: text
  - field: company_name
    label: accounting_label_company_name
    width: 10
  - field: email
    label: accounting_label_email
    width: 12
  - field: address
    label: accounting_label_address
    width: 12
  - field: zipcode
    label: accounting_label_zip_code
    width: 10
  - field: city
    label: accounting_label_city
    width: 10
```

## List Properties

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `newLabel` | Label for the "New" button |
| `component` | Detail component to open on row click |
| `disableSearch` | Disable the search functionality |
| `columns` | Array of column definitions |

## Column Properties

| Property | Description |
|----------|-------------|
| `field` | Model attribute name |
| `label` | Column header (translation key) |
| `width` | Column width (percentage or fixed) |
| `type` | Display type (text, date, etc.) |

## Livewire Component

Example: `customers-list.blade.php`

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Nywerk\Customer\Models\Customer;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'customer-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
    {
        $rows = Customer::paginate(self::PAGINATION);

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

## Next Steps

Continue with [Create a Detail View](detail-view.md) to build forms for editing records.
