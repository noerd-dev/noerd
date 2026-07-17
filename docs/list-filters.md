# List Filters

Lists can be filtered using dropdown filters (`tableFilters`). Dropdown filters allow users to narrow results by selecting a value (e.g. year, language).

In addition, every list gets Excel-style **column filters** automatically — see [Column Filters](#column-filters-excel-style) below.

## Column Filters (Excel-style)

Every filterable column shows a funnel icon in its header (revealed on header hover, always visible while active). Clicking it opens a popover where the user types a filter expression; the filter is applied on Enter or via the Apply button. This is a single generic feature in `NoerdList` + the list views — no per-list configuration or per-module duplication.

### Operator syntax

A filter expression may start with a comparison operator: `>=`, `<=`, `>`, `<`, `=`, `!=` (`<>` is accepted as `!=`). Without an operator the default depends on the column type:

| Column type | Popover UI | With operator | Without operator |
|-------------|-----------|---------------|------------------|
| `text` (default) | Text input | `=rot` exact, `!=rot` not equal, `>m` string comparison | `rot` → `LIKE %rot%` (wildcards in the value are escaped) |
| `number`, `currency` | Text input | `>0`, `<=10`, `!=5` (comma decimals accepted: `>=2,5`) | Exact match |
| `date`, `datetime` | Text input | `>=2026-01-01` (also German format `17.07.2026`) | That exact day (`whereDate =`) |
| `bool` | All / Yes / No buttons | — | — |
| `badge`/`select` (with `options`) | All + one button per option | — | Exact match on the option value |

Invalid input (non-numeric value on a number column, unparseable date, operator without value) is silently ignored — the filter is a no-op, never an error.

### Which columns are filterable

A column is filterable when it is declared in the list YAML `columns`, is not `action`, is not a dotted field (`relation.name`, `custom_attributes.x`), and exists as a real column on the model's table — the same rule as sorting. Lists that build a fully custom query (never calling `listQuery()`) show no funnels and apply no column filters.

### Behavior

- Multiple column filters combine with AND — and stack with the search field and the header `listFilters`
- Setting or clearing a filter resets pagination to page 1
- Filters persist per component in the session (`listColumnFilters.{component}`), like sorting
- Filters are mirrored into the URL as `?cf[column]=expression` — a shared link reproduces the exact
  view. On mount the URL wins over the session state. The active list view (`?view=`, including
  `default`) and the filters are written into the URL on initial page load by the url-sync script in
  the list Blade (Livewire `#[Url]` bindings only write on updates); afterwards Livewire keeps them in
  sync. Only the page-level list writes the URL — modal, compact and picker lists never do
- Compact/embedded and minimal lists render no funnels and never apply column filters (a session filter must not invisibly hide rows of an embedded widget)
- The header "Clear all filters" button (`clearAllListFilters()`) clears the column filters too; each popover also offers a per-column clear
- CSV export respects active column filters when the export query builds on `listQuery()`

### Architecture

- Expression parsing + query application: `Noerd\Services\ColumnFilterParser` (fixed operator set, values only ever bound as parameters — user input never reaches SQL text)
- State + whitelist: `NoerdList::$listColumnFilters`, `setColumnFilter()`, `clearColumnFilter()`, `filterableColumnFields()`, `applyColumnFilters()` (hooked inside `listQuery()`)
- Header UI: `noerd::components.table.column-filter`, included from `table-sort.blade.php`
- Tests: `app-modules/noerd/tests/Unit/ColumnFilterParserTest.php`, `app-modules/noerd/tests/Traits/NoerdListColumnFilterTest.php`

## How Filters Work

1. A component defines filter options via `tableFilters()` (computed property)
2. The `list-header.blade.php` template renders the filter UI (dropdowns)
3. When a user selects a filter value, it is stored in `$listFilters`
4. The query is filtered via `applyListFilters($query)` or custom logic in `with()`

## Defining a Filter

Each filter is defined by a method following the naming convention `get{Name}ListFilter()`. The method must return an array with these keys:

| Key | Description |
|-----|-------------|
| `label` | Display label for the dropdown |
| `column` | Database column to filter on |
| `type` | Filter type (currently `Picklist`) |
| `options` | Associative array of `value => label` pairs |

Example:

```php
protected function getYearListFilter(): array
{
    $filter['label'] = 'Jahr';
    $filter['column'] = 'created_at';
    $filter['type'] = 'Picklist';
    $filter['options'] = [];

    $years = Order::selectRaw('YEAR(created_at) as year')
        ->where('tenant_id', auth()->user()->selected_tenant_id)
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->toArray();

    foreach ($years as $year) {
        $filter['options']["{$year}-01-01"] = (string) $year;
    }

    return $filter;
}
```

## Creating a Filter Trait

Filters should be extracted into reusable traits so multiple list components can share them.

File location: `app-modules/{module}/src/Traits/{Name}FilterTrait.php`

Example: `app-modules/order/src/Traits/YearFilterTrait.php`

```php
<?php

namespace Nywerk\Liefertool\Traits;

use Nywerk\Liefertool\Models\Order;

trait YearFilterTrait
{
    protected function getYearListFilter(): array
    {
        $filter['label'] = 'Jahr';
        $filter['column'] = 'created_at';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        $years = Order::selectRaw('YEAR(created_at) as year')
            ->where('tenant_id', auth()->user()->selected_tenant_id)
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        foreach ($years as $year) {
            $filter['options']["{$year}-01-01"] = (string) $year;
        }

        return $filter;
    }
}
```

Another example: `app-modules/cms/src/Traits/LanguageFilterTrait.php`

```php
protected function getLanguageListFilter(): array
{
    $filter['label'] = __('Language');
    $filter['column'] = 'language';
    $filter['type'] = 'Picklist';
    $filter['options'] = [];

    $languages = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
        ->where('is_active', true)
        ->orderBy('is_default', 'desc')
        ->orderBy('name', 'asc')
        ->get();

    foreach ($languages as $language) {
        $filter['options'][$language->code] = $language->name;
    }

    return $filter;
}
```

## Using the Filter in a Component

To add filters to a list component:

1. Use the filter trait
2. The `NoerdList` trait auto-discovers all methods matching `get*ListFilter` via its default `tableFilters()` implementation

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Nywerk\Liefertool\Traits\YearFilterTrait;

new class extends Component {
    use NoerdList;
    use YearFilterTrait;

    // tableFilters() is auto-discovered from the trait — no override needed
};
```

You only need to override `tableFilters()` if you want to conditionally show filters:

```php
#[Computed]
public function tableFilters(): array
{
    if (! $this->hasMultipleLanguages()) {
        return [];
    }

    return [$this->getLanguageListFilter()];
}
```

## Filter Preselection

You can derive filter values in the `with()` method and pass them directly to the query. This is useful when the dropdown value needs to be transformed (e.g. a year selection into a date range).

Example from `orders-list`: When a year is selected in the dropdown, the date range is derived and passed directly to the repository. If no year is selected, the current year is used as default.

```php
private function getDateRange(): array
{
    $date = isset($this->listFilters['created_at'])
        ? Carbon::parse($this->listFilters['created_at'])
        : Carbon::today();

    return [
        $date->startOfYear()->format('Y-m-d'),
        $date->copy()->endOfYear()->format('Y-m-d'),
    ];
}

public function with(): array
{
    [$dateFrom, $dateTo] = $this->getDateRange();

    $rows = $orderRepository->getOrders(
        Auth::user()->selected_tenant_id,
        $this->filter,
        $this->search,
        $this->sortField,
        $this->sortAsc,
        $this->customerId,
        $dateFrom,
        $dateTo,
    );

    return [
        'listConfig' => $this->buildList($rows),
    ];
}
```

## Session Persistence

By default, `storeActiveListFilters()` in `NoerdList` is empty. Override it in components where the selected filter should persist across page loads.

Example from `pages-list` (CMS language filter):

```php
public function storeActiveListFilters(): void
{
    session(['listFilters' => $this->listFilters]);

    // Sync with selectedLanguage for page-detail consistency
    if (! empty($this->listFilters['language'])) {
        session(['selectedLanguage' => $this->listFilters['language']]);
    }
}
```

The `storeActiveListFilters` method is called automatically by the UI via `wire:change="storeActiveListFilters"` on the filter dropdown.

## Security

The `NoerdList` trait extracts allowed columns from the `tableFilters()` output. Only columns returned by the filter methods can be filtered on, preventing users from manipulating filter parameters to query arbitrary columns.

You can also define `ALLOWED_TABLE_FILTERS` as a constant in your component for an explicit whitelist:

```php
protected const ALLOWED_TABLE_FILTERS = [
    'vehicle_id',
    'created_at',
];
```
