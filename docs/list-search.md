# List Search

The search functionality allows users to filter list data by typing in a search field. The search is applied via the `listQuery()` method in `NoerdList`, which reads searchable columns from the YAML configuration.

## Enabling/Disabling the Search Field

In the YAML configuration, use `disableSearch` to control the search field visibility:

```yaml
title: Customers
disableSearch: false  # Search is enabled (default)
columns:
  - field: name
    label: Name
```

To disable the search field:

```yaml
disableSearch: true
```

## How Search Works

1. The `NoerdList` trait provides a `$search` property bound to the search input via `wire:model.live="search"`
2. When the user types, the search value is available in `$this->search`
3. `listQuery()` applies WHERE conditions based on `searchableColumns` (or all column fields as fallback)
4. Filtered results are returned

## Using `listQuery()` in a List Component

The `listQuery()` method handles search and sort automatically based on YAML configuration:

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
        $rows = $this->listQuery(Customer::class)->paginate(self::PAGINATION);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
```

## Searchable Columns Configuration

By default, `listQuery()` searches across all columns defined in the YAML `columns` array. To limit search to specific fields, use `searchableColumns`:

```yaml
title: Customers
searchableColumns:
  - name
  - company_name
  - email
  - zipcode
columns:
  - field: name
    label: Name
    width: 15
  - field: company_name
    label: Company
    width: 15
```

If `searchableColumns` is not defined, all `columns[].field` values are used as searchable fields.

## Architecture Overview

```
User types in search field
        ↓
wire:model.live="search" updates $this->search
        ↓
listQuery() reads searchableColumns from YAML (or all columns)
        ↓
WHERE conditions applied with LIKE operators
        ↓
Sort applied based on $this->sortField / $this->sortAsc
        ↓
Filtered and sorted results returned
```

## Eager Loading

To add eager loading, chain `->with()` on the query:

```php
$rows = $this->listQuery(BookingType::class)
    ->with(['staff', 'slots'])
    ->paginate(self::PAGINATION);
```

## Manual Search (Fallback)

For lists with fixed custom sorting (e.g., `orderBy('sort')`) where `listQuery()` would override the sort, use manual search:

```php
public function with()
{
    $rows = Menu::query()
        ->when($this->search, function ($query): void {
            $query->where(function ($query): void {
                $query->where('name', 'like', '%' . $this->search . '%');
            });
        })
        ->orderBy('sort')
        ->paginate(self::PAGINATION);

    return [
        'listConfig' => $this->buildList($rows),
    ];
}
```

## Default Sorting

To set a default sort order for a list, use the `setDefaultSort()` method in your `mount()` method:

```php
public function mount(): void
{
    $this->mountList();
    $this->setDefaultSort('invoice_date', false);  // Sort by invoice_date descending
}
```

The method signature is:

```php
protected function setDefaultSort(string $field, bool $ascending = false): void
```

- `$field`: The column name to sort by
- `$ascending`: `true` for ascending (A-Z, oldest first), `false` for descending (Z-A, newest first)

## Not Sortable Columns

By default, all columns in a list are sortable. To disable sorting for specific columns, use `notSortableColumns` in the YAML configuration:

```yaml
title: Orders
notSortableColumns:
  - computed_field
  - relation_display
columns:
  - field: name
    label: Name
    width: 15
  - field: computed_field
    label: Computed
    width: 10
```

Columns listed in `notSortableColumns` will display their label as plain text instead of a clickable sort button. Clicking `sortBy()` for these fields will be ignored.

## Best Practices

1. **Use `listQuery()`**: Prefer the automatic approach via `listQuery()` for all standard lists

2. **Use `searchableColumns`**: Define specific searchable fields in YAML when not all columns should be searchable

3. **Consider performance**: For large datasets, add database indexes on searchable columns

4. **Keep it simple**: The `listQuery()` approach keeps list components clean and consistent

## Related Documentation

- [List View](list-view.md) - Basic list configuration
- [List Filters](list-filters.md) - Dropdown filters for lists
