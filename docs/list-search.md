# List Search

The search functionality allows users to filter list data by typing in a search field. The search is automatically applied via Global Scopes when the model uses the `HasListScopes` trait.

## Enabling/Disabling the Search Field

In the YAML configuration, use `disableSearch` to control the search field visibility:

```yaml
title: accounting_label_customers
disableSearch: false  # Search is enabled (default)
columns:
  - field: name
    label: accounting_label_name
```

To disable the search field:

```yaml
disableSearch: true
```

## How Search Works

1. The `NoerdList` trait provides a `$search` property bound to the search input via `wire:model.live="search"`
2. When the user types, `updatedSearch()` syncs the search value to `ListQueryContext`
3. The `SearchScope` (Global Scope) automatically reads from `ListQueryContext` and applies the filter
4. The model's `$searchable` property defines which fields are searched

## Setting Up Search in a Model

Add the `HasListScopes` trait and define the `$searchable` property:

```php
<?php

namespace Nywerk\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\HasListScopes;

class Customer extends Model
{
    use HasListScopes;

    /**
     * Fields that are searchable via the search scope.
     */
    protected array $searchable = [
        'name',
        'company_name',
        'email',
        'zipcode',
    ];
}
```

## Complete List Component Example

With `HasListScopes` and `$searchable` configured in the model, the list component is minimal:

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
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
```

No manual search logic is needed in `with()` - the `SearchScope` handles it automatically.

## Architecture Overview

```
User types in search field
        ↓
wire:model.live="search" updates $this->search
        ↓
updatedSearch() syncs to ListQueryContext
        ↓
Model query executes (e.g., Customer::paginate())
        ↓
SearchScope (Global Scope) reads from ListQueryContext
        ↓
SearchScope applies WHERE conditions based on $searchable
        ↓
Filtered results returned
```

## The HasListScopes Trait

The trait provides:

- **Global Scopes**: Automatically registers `SearchScope` and `SortScope`
- **`getSearchableFields()`**: Returns the `$searchable` array
- **`scopeSearch()`**: Manual scope for backward compatibility
- **`scopeSorted()`**: Manual scope for sorting

```php
trait HasListScopes
{
    public static function bootHasListScopes(): void
    {
        static::addGlobalScope(new SearchScope());
        static::addGlobalScope(new SortScope());
    }

    public function getSearchableFields(): array
    {
        return $this->searchable ?? [];
    }
}
```

## Manual Search (Fallback)

For models without `HasListScopes` or for custom search logic, use the manual approach:

```php
public function with()
{
    $rows = SomeModel::query()
        ->when($this->search, function ($query): void {
            $query->where(function ($query): void {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        })
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

The method automatically syncs the sort state to `ListQueryContext`, ensuring the `SortScope` applies the correct ordering.

## Best Practices

1. **Use `HasListScopes`**: Prefer the automatic approach via the trait and `$searchable` property

2. **Choose searchable fields wisely**: Only include fields users would expect to search

3. **Consider performance**: For large datasets, add database indexes on searchable columns

4. **Keep it simple**: The automatic approach keeps list components clean and consistent

## Related Documentation

- [List View](list-view.md) - Basic list configuration
- [List Filters](list-filters.md) - Dropdown filters for lists
