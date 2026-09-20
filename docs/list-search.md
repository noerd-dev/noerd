# List Search

The search field of a list filters its rows while the user types. Search and sorting are applied by
`NoerdList::listQuery()`, driven by the list YAML — a slim list component needs no code for either.

## Enabling/Disabling the Search Field

The search field is shown by default; hide it with `disableSearch: true` in the list YAML:

```yaml
title: Items
disableSearch: true
columns:
  - field: name
    label: Name
```

## How Search Works

1. The `NoerdList` trait provides a `$search` property bound to the search input via `wire:model.live.debounce.300ms="search"` (`noerd::components.table.list-search`)
2. `listQuery()` reads `searchableColumns` from the YAML (or every `columns[].field` as fallback) and keeps only the fields that are real columns of the model's table
3. The term is matched as an OR-combined "contains" condition over those columns (`ColumnFilterParser::applyLikeContains()`, LIKE wildcards in the input escaped) inside ONE nested where group — so the search stacks with the column filters and any extra `where` of a custom query
4. The query is ordered by `$sortField` / `$sortAsc` (a field that is no real column falls back to `id`)

## Searchable Columns Configuration

To limit the search to specific fields, use `searchableColumns`:

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
(`custom_attributes.x`, `category.name`), accessors and computed fields are skipped silently. For
large tables, index the searchable columns.

## Eager Loading

`listQuery()` eager-loads the relations of dotted relation columns (`category.name`) itself. For
further relations (accessors, row transformations) override `listData()` and chain `->with()`:

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

For lists with fixed custom sorting (e.g., `orderBy('sort')`) where `listQuery()` would override the sort, apply the search yourself inside a `listData()` override. Use `ColumnFilterParser::applyLikeContains()` — the same driver-portable "contains" match `listQuery()` uses (a bare backslash-escaped `like` matches nothing on sqlite):

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

Such a list gets no column filters (they are applied by `listQuery()`, see
[List Filters](list-filters.md)).

## Default Sorting

Default sorting is configuration (`defaultSort:` in the list YAML), never component code — see
[List View — Default Sorting](list-view.md#default-sorting).

## Not Sortable Columns

All columns are sortable by default. To disable sorting for specific columns, list them in
`notSortableColumns`:

```yaml
title: Orders
notSortableColumns:
  - computed_field
columns:
  - field: name
    label: Name
  - field: computed_field
    label: Computed
```

Such a column shows its label as plain text instead of a sort button, and a `sortBy()` call for it
is ignored.

The rule lives once in `NoerdList::isSortableColumn($field, $notSortableColumns)`: a column is
sortable when it is not `action`, not listed in `notSortableColumns` and **not dotted** — a
`custom_attributes.x` or `category.name` path resolves at render time, so the query cannot order by
it (such columns can still be filtered, see [List Filters](list-filters.md)). `listQuery()` also
refuses to sort on an attribute the model hides (`$hidden`). Lists in
[grid mode](list-view.md#grid-mode-card-layout) render no table header and surface exactly the same
set of columns in a sort dropdown above the cards, plus `setSortDirection()` entries for an explicit
ascending/descending choice.

## Related Documentation

- [List View](list-view.md) - Basic list configuration
- [List Filters](list-filters.md) - Dropdown filters and Excel-style column filters
