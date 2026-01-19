# Lists

Lists display data in tables with search, sorting, and pagination.

## File Structure

```
app-modules/{module}/resources/views/livewire/{resources}-list.blade.php
app-configs/{app}/lists/{resources}-list.yml
```

**Naming Convention:** Always use plural form for lists (e.g., `customers-list`, `users-list`).

## Example List

```php
<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use App\Models\Customer;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    // The Noerd trait provides search, sorting, pagination and modal handling
    use Noerd;

    // Unique identifier for this list - must match the YAML filename
    public const COMPONENT = 'customers-list';

    // Called when a row is clicked or "New" button is pressed
    // Opens the detail modal for editing or creating
    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'customer-detail',  // The detail component to open
            source: self::COMPONENT,        // Used for reloading the list after save
            arguments: ['customerId' => $modelId, 'relationId' => $relationId],
        );
    }

    // Provides data to the Blade view
    // Must return 'rows' (paginated data) and 'listSettings' (YAML config)
    public function with()
    {
        // Build the query with tenant filter, sorting, and search
        $rows = Customer::where('tenant_id', Auth::user()->selected_tenant_id)
            // $sortField and $sortAsc come from the Noerd trait
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            // $search comes from the Noerd trait, bound to the search input
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            // PAGINATION constant is defined in the Noerd trait (default: 15)
            ->paginate(self::PAGINATION);

        // Load table configuration from YAML file
        $listConfig = $this->getListConfig();

        return [
            'rows' => $rows,
            'listSettings' => $listConfig,
        ];
    }
} ?>

{{-- Page wrapper with modal support --}}
<x-noerd::page :disableModal="$disableModal">
    <div>
        {{-- Renders the complete table: header, search, columns, pagination --}}
        @include('noerd::components.table.table-build', ['listSettings' => $listConfig])
    </div>
</x-noerd::page>
```

## Required Constants

| Constant | Description |
|----------|-------------|
| `COMPONENT` | Unique identifier for the list (e.g., `customers-list`) |

## Key Methods

### listAction()

Opens the detail modal when a row is clicked or the "New" button is pressed:

```php
public function listAction(mixed $modelId = null, mixed $relationId = null): void
{
    $this->dispatch(
        event: 'noerdModal',
        component: 'customer-detail',
        source: self::COMPONENT,
        arguments: ['customerId' => $modelId, 'relationId' => $relationId],
    );
}
```

### with()

Returns data for the view. Must include:
- `rows`: Paginated query results
- `tableConfig`: YAML configuration loaded via `$this->getListConfig()`

```php
public function with()
{
    $rows = Customer::where('tenant_id', Auth::user()->selected_tenant_id)
        ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->paginate(self::PAGINATION);

    return [
        'rows' => $rows,
        'listSettings' => $this->getListConfig(),
    ];
}
```

## YAML Configuration

Create a YAML file at `app-configs/{app}/lists/{resources}-list.yml`:

```yaml
title: customer_label_customers
newLabel: customer_label_new_customer
component: customer-detail
columns:
  - { field: name, label: customer_label_name, width: 10 }
  - { field: email, label: customer_label_email, width: 10 }
  - { field: created_at, label: customer_label_created, width: 8, type: date }
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Page title (translation key) |
| `newLabel` | string | Label for "New" button |
| `component` | string | Detail component to open |
| `disableSearch` | bool | Hide search field |
| `columns` | array | Column definitions |

### Column Options

| Option | Type | Description |
|--------|------|-------------|
| `field` | string | Model attribute (supports dot notation: `category.name`) |
| `label` | string | Column header (translation key) |
| `width` | int | Column width (1-24) |
| `type` | string | Display type: `text`, `date`, `boolean`, `badge` |
| `sortable` | bool | Enable sorting (default: true) |

## Blade Template

```blade
<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build', ['listSettings' => $listConfig])
    </div>
</x-noerd::page>
```

The `table-build` include handles:
- Header with title and "New" button
- Search field
- Table with columns from YAML
- Pagination
- Sorting

## Noerd Trait Properties

The `Noerd` trait provides these properties for lists:

| Property | Type | Description |
|----------|------|-------------|
| `$search` | string | Current search term |
| `$sortField` | string | Current sort field |
| `$sortAsc` | bool | Sort direction |
| `$disableModal` | bool | Disable modal behavior |