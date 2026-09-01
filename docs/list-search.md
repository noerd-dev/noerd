# List Search

The search functionality allows users to filter list data by typing in a search field. The search is applied via the `listQuery()` method in `NoerdList`, which reads searchable columns from the YAML configuration.

## Enabling/Disabling the Search Field

In the YAML configuration, use `disableSearch` to control the search field visibility:

```yaml
title: Items
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

1. The `NoerdList` trait provides a `$search` property bound to the search input via `wire:model.live.debounce.300ms="search"` (`noerd::components.table.list-search`)
2. When the user types, the search value is available in `$this->search`
3. `listQuery()` applies WHERE conditions based on `searchableColumns` (or every `columns[].field` as fallback) — only fields that are real columns of the model's table take part
4. Filtered results are returned

## Using `listQuery()` in a List Component

The `listQuery()` method handles search and sort automatically based on YAML configuration:

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Inventory\Models\Item;

new class extends Component {
    use NoerdList;

    public $listModel = Item::class;
    public ?string $detailRoute = 'inventory.item.detail';
    public $detailComponent = 'inventory::item-detail';
};
?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
```

## Searchable Columns Configuration

By default, `listQuery()` searches across the columns defined in the YAML `columns` array. To limit search to specific fields, use `searchableColumns`:

```yaml
title: Items
searchableColumns:
  - name
  - sku
  - description
columns:
  - field: name
    label: Name
    width: 15
  - field: sku
    label: SKU
    width: 15
```

If `searchableColumns` is not defined, all `columns[].field` values are candidates. In both cases
only **real columns of the model's table** are searched (`tableHasColumn()`): dotted fields
(`custom_attributes.x`, `category.name`), accessors and computed fields are skipped silently.

## Architecture Overview

```
User types in search field
        ↓
wire:model.live.debounce.300ms="search" updates $this->search
        ↓
listQuery() reads searchableColumns from YAML (or all columns) and keeps the real table columns
        ↓
OR-combined "contains" conditions (ColumnFilterParser::applyLikeContains, wildcards escaped)
        ↓
Sort applied based on $this->sortField / $this->sortAsc
        ↓
Filtered and sorted results returned
```

## Eager Loading

To add eager loading, override `listData()` and chain `->with()` on the query:

```php
public function listData(): array
{
    $rows = $this->listQuery($this->listModel)
        ->with(['staff', 'slots'])
        ->paginate($this->perPage);

    return $this->buildList($rows);
}
```

## Manual Search (Fallback)

For lists with fixed custom sorting (e.g., `orderBy('sort')`) where `listQuery()` would override the sort, apply the search yourself inside a `listData()` override. Use `ColumnFilterParser::applyLikeContains()` — the same driver-portable "contains" match `listQuery()` uses, with the LIKE wildcards in the input escaped:

```php
use Noerd\Services\ColumnFilterParser;

public function listData(): array
{
    $rows = Menu::query()
        ->when($this->search, function ($query): void {
            $query->where(function ($query): void {
                ColumnFilterParser::applyLikeContains($query, 'name', $this->search);
            });
        })
        ->orderBy('sort')
        ->paginate($this->perPage);

    return $this->buildList($rows);
}
```

## Default Sorting

Default sorting is configuration (`defaultSort:` in the list YAML), never component code — see
[List View — Default Sorting](list-view.md#default-sorting).

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

The rule lives once in `NoerdList::isSortableColumn($field, $notSortableColumns)`: a column is
sortable when it is not `action`, not listed in `notSortableColumns` and **not dotted** — a
`custom_attributes.x` or `category.name` path resolves at render time, so the query cannot order by
it (such columns can still be filtered, see [List Filters](list-filters.md)). Lists in
[grid mode](list-view.md#grid-mode-card-layout) render no table header and surface exactly the same
set of columns in a sort dropdown above the cards, plus `setSortDirection()` entries for an explicit
ascending/descending choice.

## Best Practices

1. **Use `listQuery()`**: Prefer the automatic approach via `listQuery()` for all standard lists

2. **Use `searchableColumns`**: Define specific searchable fields in YAML when not all columns should be searchable

3. **Consider performance**: For large datasets, add database indexes on searchable columns

4. **Keep it simple**: The `listQuery()` approach keeps list components clean and consistent

## Related Documentation

- [List View](list-view.md) - Basic list configuration
- [List Filters](list-filters.md) - Dropdown filters for lists
